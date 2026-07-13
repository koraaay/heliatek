<?php

declare(strict_types=1);

namespace Heliatek\Sitepackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Legt die zweisprachige Beispiel-Landingpage "HeliaSol v2" (Award-Design)
 * inklusive Rechtsseiten (Impressum, Datenschutz, AGB) als echte TYPO3-Seiten an.
 *
 * Aufruf:  vendor/bin/typo3 heliatek:seed:award-page
 */
#[AsCommand(
    name: 'heliatek:seed:award-page',
    description: 'Legt die HeliaSol-v2-Landingpage (DE/EN) samt Rechtsseiten als TYPO3-Seiten an.'
)]
final class SeedAwardPageCommand extends Command
{
    private int $newCounter = 0;
    private int $lang = 0;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        Bootstrap::initializeBackendAuthentication();
        $backendUser = GeneralUtility::makeInstance(CommandLineUserAuthentication::class);
        $backendUser->authenticate();
        $GLOBALS['BE_USER'] = $backendUser;
        $GLOBALS['BE_USER']->workspace = 0;

        $this->copyDemoAssets($io);

        $rootUid = $this->ensureRootPage($io);
        if ($rootUid === 0) {
            $io->error('Root-Seite konnte nicht angelegt werden.');
            return Command::FAILURE;
        }
        $io->writeln('Root-Seite: uid ' . $rootUid);

        $this->clearAll($rootUid);
        $io->writeln('Alte Inhalte/Seiten entfernt.');

        // Englische Übersetzung der Root-Seite
        $this->createPageTranslation($rootUid, 'HeliaSol v2', $io);

        // Award-Seite in DE und EN aufbauen
        foreach ([0, 1] as $lang) {
            $this->lang = $lang;
            $io->section($lang === 0 ? 'Deutsch (/)' : 'English (/en/)');
            foreach ($this->sections() as $i => $section) {
                $this->createElement($rootUid, $i + 1, $section, $io);
            }
        }

        // Rechtsseiten (DE + EN)
        $this->createLegalPages($rootUid, $io);

        GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->flushCaches();
        $io->success('HeliaSol-v2-Seite (DE/EN) + Rechtsseiten aufgebaut. Frontend: "/" und "/en/".');
        return Command::SUCCESS;
    }

    private function t(string $de, string $en): string
    {
        return $this->lang === 1 ? $en : $de;
    }

    private function newId(string $prefix): string
    {
        return 'NEW_' . $prefix . '_' . (++$this->newCounter);
    }

    private function copyDemoAssets(SymfonyStyle $io): void
    {
        $source = GeneralUtility::getFileAbsFileName('EXT:heliatek_sitepackage/Resources/Public/DemoImages');
        $target = Environment::getPublicPath() . '/fileadmin/heliatek';
        if (!is_dir($target)) {
            GeneralUtility::mkdir_deep($target);
        }
        foreach (glob($source . '/*.svg') ?: [] as $file) {
            copy($file, $target . '/' . basename($file));
        }
        $io->writeln('Demo-Bilder nach fileadmin/heliatek/ kopiert.');
    }

    private function ensureRootPage(SymfonyStyle $io): int
    {
        $conn = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('pages');
        $existing = $conn->select(['uid'], 'pages', ['is_siteroot' => 1], [], ['uid' => 'ASC'], 1)->fetchAssociative();
        if ($existing) {
            return (int)$existing['uid'];
        }
        $dh = GeneralUtility::makeInstance(DataHandler::class);
        $newPage = $this->newId('page');
        $dh->start([
            'pages' => [
                $newPage => [
                    'pid' => 0,
                    'title' => 'HeliaSol v2',
                    'doktype' => 1,
                    'is_siteroot' => 1,
                    'hidden' => 0,
                    'slug' => '/',
                ],
            ],
        ], []);
        $dh->process_datamap();
        if (!empty($dh->errorLog)) {
            $io->warning(implode("\n", $dh->errorLog));
        }
        return (int)($dh->substNEWwithIDs[$newPage] ?? 0);
    }

    private function createPageTranslation(int $parentUid, string $title, SymfonyStyle $io): void
    {
        $conn = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('pages');
        $conn->insert('pages', array_merge($this->base(0), [
            'pid' => 0,
            'sys_language_uid' => 1,
            'l10n_parent' => $parentUid,
            'l10n_source' => $parentUid,
            'title' => $title,
            'doktype' => 1,
            'slug' => '/',
        ]));
    }

    private function clearAll(int $rootUid): void
    {
        $pool = GeneralUtility::makeInstance(ConnectionPool::class);
        $collectionTables = [
            'hero_buttons', 'feature_items', 'stat_items', 'compare_bars',
            'spec_items', 'card_items', 'accordion_items', 'cta_buttons', 'compare_cards',
        ];
        foreach ($collectionTables as $table) {
            $pool->getConnectionForTable($table)->executeStatement('DELETE FROM ' . $table);
        }
        $pool->getConnectionForTable('sys_file_reference')->executeStatement('DELETE FROM sys_file_reference');
        $pool->getConnectionForTable('tt_content')->executeStatement('DELETE FROM tt_content');
        // alle Seiten außer der Root-Seite (Rechtsseiten + alte Übersetzungen)
        $pool->getConnectionForTable('pages')->executeStatement(
            'DELETE FROM pages WHERE uid <> ?',
            [$rootUid]
        );
    }

    private function fileUid(string $path): int
    {
        $factory = GeneralUtility::makeInstance(ResourceFactory::class);
        $file = $factory->getFileObjectFromCombinedIdentifier('1:/heliatek/' . $path);
        return $file->getUid();
    }

    private function base(int $lang): array
    {
        $now = time();
        return [
            'pid' => 0,
            'tstamp' => $now,
            'crdate' => $now,
            'deleted' => 0,
            'hidden' => 0,
            'sys_language_uid' => $lang,
        ];
    }

    private function addFileReference(int $pid, string $table, string $field, int $foreignUid, int $fileUid, int $sorting): void
    {
        $conn = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_file_reference');
        $row = array_merge($this->base($this->lang), [
            'pid' => $pid,
            'uid_local' => $fileUid,
            'tablenames' => $table,
            'fieldname' => $field,
            'uid_foreign' => $foreignUid,
            'sorting_foreign' => $sorting,
        ]);
        $conn->insert('sys_file_reference', $row);
    }

    /**
     * @param array $section [ctype, fields[], collections[table=>[rows]], files[field=>path]]
     */
    private function createElement(int $pid, int $sorting, array $section, SymfonyStyle $io): void
    {
        $pool = GeneralUtility::makeInstance(ConnectionPool::class);
        $ttConn = $pool->getConnectionForTable('tt_content');

        $row = array_merge($this->base($this->lang), [
            'pid' => $pid,
            'CType' => $section['ctype'],
            'colPos' => 0,
            'sorting' => $sorting * 256,
        ], $section['fields'] ?? []);

        foreach (($section['collections'] ?? []) as $table => $rows) {
            $row[$table] = count($rows);
        }
        foreach (($section['files'] ?? []) as $field => $path) {
            $row[$field] = 1;
        }

        $ttConn->insert('tt_content', $row);
        $parentUid = (int)$ttConn->lastInsertId();

        foreach (($section['collections'] ?? []) as $table => $rows) {
            $conn = $pool->getConnectionForTable($table);
            $pos = 1;
            foreach ($rows as $childRow) {
                $files = $childRow['__files'] ?? [];
                unset($childRow['__files']);
                foreach ($files as $field => $path) {
                    $childRow[$field] = 1;
                }
                $conn->insert($table, array_merge($this->base($this->lang), [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $parentUid,
                    'sorting' => $pos * 256,
                ], $childRow));
                $childUid = (int)$conn->lastInsertId();
                foreach ($files as $field => $path) {
                    $this->addFileReference($pid, $table, $field, $childUid, $this->fileUid($path), 1);
                }
                $pos++;
            }
        }

        $fpos = 1;
        foreach (($section['files'] ?? []) as $field => $path) {
            $this->addFileReference($pid, 'tt_content', $field, $parentUid, $this->fileUid($path), $fpos++);
        }

        $io->writeln('  ✓ ' . $section['ctype']);
    }

    private function createLegalPages(int $rootUid, SymfonyStyle $io): void
    {
        $pages = [
            ['de' => 'Impressum', 'en' => 'Imprint', 'slug' => 'impressum'],
            ['de' => 'Datenschutzerklärung', 'en' => 'Privacy Policy', 'slug' => 'datenschutz'],
            ['de' => 'AGB', 'en' => 'Terms & Conditions', 'slug' => 'agb'],
        ];
        $pool = GeneralUtility::makeInstance(ConnectionPool::class);
        $pagesConn = $pool->getConnectionForTable('pages');
        $sorting = 256;
        foreach ($pages as $p) {
            // DE-Seite
            $dh = GeneralUtility::makeInstance(DataHandler::class);
            $nid = $this->newId('legal');
            $dh->start(['pages' => [$nid => [
                'pid' => $rootUid,
                'title' => $p['de'],
                'doktype' => 1,
                'hidden' => 0,
                'nav_hide' => 1,
                'slug' => '/' . $p['slug'],
                'sorting' => $sorting,
            ]]], []);
            $dh->process_datamap();
            $legalUid = (int)($dh->substNEWwithIDs[$nid] ?? 0);
            if ($legalUid === 0) {
                continue;
            }
            // EN-Übersetzung der Seite
            $pagesConn->insert('pages', array_merge($this->base(0), [
                'pid' => $rootUid,
                'sys_language_uid' => 1,
                'l10n_parent' => $legalUid,
                'l10n_source' => $legalUid,
                'title' => $p['en'],
                'doktype' => 1,
                'nav_hide' => 1,
                'slug' => '/' . $p['slug'],
            ]));
            // Platzhalter-Inhalt in beiden Sprachen
            foreach ([0, 1] as $lang) {
                $this->lang = $lang;
                $this->createElement($legalUid, 1, [
                    'ctype' => 'heliatek_text',
                    'fields' => [
                        'headline' => $lang === 0 ? $p['de'] : $p['en'],
                        'bodytext' => $lang === 0
                            ? '<p>Platzhalter — bitte rechtsverbindlichen Text einsetzen.</p>'
                            : '<p>Placeholder — please insert the legally binding text.</p>',
                        'align' => 'left',
                        'background' => 'default',
                    ],
                ], $io);
            }
            $sorting += 256;
        }
        // Reihenfolge Impressum → Datenschutz → AGB (Erstellungsreihenfolge = uid)
        $pagesConn->executeStatement(
            'UPDATE pages SET sorting = uid * 256 WHERE pid = ? AND sys_language_uid = 0',
            [$rootUid]
        );
        $this->lang = 0;
        $io->writeln('Rechtsseiten (Impressum, Datenschutz, AGB) angelegt.');
    }

    /**
     * Inhalt der Award-Seite, Sektion für Sektion (zweisprachig über t()).
     */
    private function sections(): array
    {
        return [
            // 1 — Hero (dunkel)
            [
                'ctype' => 'heliatek_hero',
                'fields' => [
                    'badge' => $this->t('Neu · Arbeitstitel HeliaSol v2', 'New · working title HeliaSol v2'),
                    'headline' => $this->t('Die nächste Generation der Solarfolie.', 'The next generation of solar film.'),
                    'teaser' => $this->t(
                        'HeliaSol v2 — weltweit erstes flexibles, perowskitbasiertes Solarmodul. Entwickelt und gefertigt in Dresden. Gleiche Haltung, neue Technologie.',
                        'HeliaSol v2 — the world’s first flexible, perovskite-based solar module. Developed and manufactured in Dresden. Same values, new technology.'
                    ),
                ],
                'collections' => [
                    'hero_buttons' => [
                        ['label' => $this->t('Jetzt informieren', 'Learn more'), 'link' => '#kontakt', 'style' => 'primary'],
                        ['label' => $this->t('Technologie-Vergleich', 'Technology comparison'), 'link' => '#vergleich', 'style' => 'secondary'],
                    ],
                ],
            ],
            // 2 — Produktbild (Konzept) mit Sheen
            [
                'ctype' => 'heliatek_image',
                'fields' => [
                    'annotation' => $this->t('Konzeptbild — echtes Produktfoto folgt', 'Concept image — real product photo to follow'),
                    'aspect' => '16/9',
                    'framed' => 1,
                    'sheen' => 1,
                ],
                'files' => ['image' => 'product-mockup-grey.svg'],
            ],
            // 3 — Was bleibt (USP-Grid)
            [
                'ctype' => 'heliatek_featuregrid',
                'fields' => [
                    'headline' => $this->t('Die Heliatek-DNA', 'The Heliatek DNA'),
                    'intro' => $this->t(
                        'Alles, was HeliaSol ausmacht, trägt auch die nächste Generation: ultra-leicht, flexibel, selbstklebend — und einfach zu installieren, wo herkömmliche Paneele scheitern.',
                        'Everything that defines HeliaSol carries over to the next generation: ultra-light, flexible, self-adhesive — and easy to install where conventional panels fail.'
                    ),
                ],
                'collections' => [
                    'feature_items' => [
                        ['title' => $this->t('Ultra-Leicht', 'Ultra-light'), '__files' => ['icon' => 'usp-lightweight.svg']],
                        ['title' => $this->t('Flexibel', 'Flexible'), '__files' => ['icon' => 'usp-flexibility.svg']],
                        ['title' => $this->t('Wirklich Grün', 'Truly green'), '__files' => ['icon' => 'usp-truly-green.svg']],
                        ['title' => $this->t('Einfache Installation', 'Easy to install'), '__files' => ['icon' => 'usp-easy-install.svg']],
                        ['title' => $this->t('Temperatur-unabhängig', 'Temperature-stable'), '__files' => ['icon' => 'usp-temperature.svg']],
                        ['title' => $this->t('Made in Dresden', 'Made in Dresden')],
                    ],
                ],
            ],
            // 4 — Vorher/Nachher-Slider (dunkel)
            [
                'ctype' => 'heliatek_imagecompare',
                'fields' => [
                    'before_label' => $this->t('Bisher — HeliaSol (OPV)', 'Before — HeliaSol (OPV)'),
                    'after_label' => $this->t('Neu — HeliaSol v2 (Perowskit)', 'New — HeliaSol v2 (perovskite)'),
                    'dark_background' => 1,
                ],
                'files' => [
                    'before_image' => 'opv-closeup.svg',
                    'after_image' => 'product-mockup-grey.svg',
                ],
            ],
            // 5 — Technologie-Vergleich (dunkel): Balken + Spec-Liste
            [
                'ctype' => 'heliatek_techcompare',
                'fields' => [
                    'kicker' => '',
                    'headline' => $this->t('Von Organisch zu Perowskit', 'From organic to perovskite'),
                    'intro' => $this->t(
                        'Links die bewährte organische Solarfolie, rechts die neue Perowskit-Generation.',
                        'On the left the proven organic solar film, on the right the new perovskite generation.'
                    ),
                    'dark_background' => 1,
                    'bars_label' => $this->t(
                        'Effizienz im Vergleich (Richtwert — finale Zahlen folgen mit dem Datenblatt)',
                        'Efficiency compared (indicative — final figures with the datasheet)'
                    ),
                ],
                'collections' => [
                    'compare_bars' => [
                        ['label' => 'HeliaSol (OPV)', 'bar_value' => '1×', 'percent' => 38, 'bar_color' => '#8b5fbf', 'emphasized' => 0],
                        ['label' => $this->t('HeliaSol v2 (Perowskit)', 'HeliaSol v2 (perovskite)'), 'bar_value' => '~2×', 'percent' => 76, 'bar_color' => '#8a8d88', 'emphasized' => 1],
                    ],
                    'spec_items' => [
                        ['spec_label' => $this->t('Zelltechnologie', 'Cell technology'), 'spec_value' => $this->t('Perowskit statt Organisch', 'Perovskite instead of organic')],
                        ['spec_label' => $this->t('Erscheinung', 'Appearance'), 'spec_value' => $this->t('Matt-Grau statt Violett-changierend', 'Matte grey instead of iridescent violet')],
                        ['spec_label' => $this->t('Herstellung', 'Manufacturing'), 'spec_value' => $this->t('Weiterhin Dresden, Deutschland', 'Still Dresden, Germany')],
                        ['spec_label' => $this->t('Montage', 'Mounting'), 'spec_value' => $this->t('Weiterhin selbstklebend, werkzeugarm', 'Still self-adhesive, minimal tooling')],
                    ],
                ],
            ],
            // 6 — Kennzahlen (Mint-Band)
            [
                'ctype' => 'heliatek_stats',
                'fields' => [
                    'headline' => $this->t('Kennzahlen auf einen Blick', 'Key figures at a glance'),
                    'dark_background' => 0,
                    'footnote' => $this->t(
                        'Vorläufige Ziel- und Richtwerte. Verbindliche Spezifikationen veröffentlichen wir mit dem finalen Datenblatt zur Markteinführung.',
                        'Preliminary target and indicative values. Binding specifications will be published with the final datasheet at market launch.'
                    ),
                ],
                'collections' => [
                    'stat_items' => [
                        ['value' => '~2×', 'label' => $this->t('Effizienz ggü. OPV (Richtwert)', 'Efficiency vs. OPV (indicative)')],
                        ['value' => '<2 kg', 'label' => $this->t('Gewicht pro Modul (Ziel)', 'Weight per module (target)')],
                        ['value' => '≈2 mm', 'label' => $this->t('Dicke ohne Anschlussdose (Ziel)', 'Thickness excl. junction box (target)')],
                        ['value' => '50 cm', 'label' => $this->t('Min. Biegeradius', 'Min. bending radius')],
                    ],
                ],
            ],
            // 7 — Technologie / Schichtaufbau (Text & Bild)
            [
                'ctype' => 'heliatek_textmedia',
                'fields' => [
                    'headline' => $this->t('Folie bleibt Folie.', 'A film stays a film.'),
                    'bodytext' => $this->t(
                        '<p>Der bewährte Schichtaufbau — aktive Zellschicht zwischen Barrierefolien, rückseitig vollflächig klebend — bleibt erhalten. Neu ist die aktive Schicht: Perowskit-Zellen ersetzen die organischen Zellen und liefern deutlich mehr Energie pro Fläche.</p>',
                        '<p>The proven layer structure — an active cell layer between barrier films, fully adhesive on the back — stays the same. What’s new is the active layer: perovskite cells replace the organic cells and deliver significantly more energy per area.</p>'
                    ),
                    'media_position' => 'right',
                    'background' => 'alt',
                ],
                'files' => ['image' => 'schichtaufbau.svg'],
            ],
            // 8 — Referenzen (Karten)
            [
                'ctype' => 'heliatek_cardgrid',
                'fields' => [
                    'headline' => $this->t('Überall dort, wo Paneele scheitern', 'Everywhere conventional panels fail'),
                    'intro' => $this->t(
                        'Bewährte HeliaSol-Installationen zeigen die Einsatzfelder: Leichtbaudächer, Fassaden, gebogene Flächen — HeliaSol v2 übernimmt sie mit fast doppelter Energieausbeute.',
                        'Proven HeliaSol installations show the use cases: lightweight roofs, façades, curved surfaces — HeliaSol v2 takes them on with almost double the energy yield.'
                    ),
                ],
                'collections' => [
                    'card_items' => [
                        ['title' => $this->t('Leichtbaudach — Barcelona', 'Lightweight roof — Barcelona'), 'text' => '', '__files' => ['image' => 'ref-barcelona.svg']],
                        ['title' => $this->t('Gebogene Fläche — Denekamp', 'Curved surface — Denekamp'), 'text' => '', '__files' => ['image' => 'ref-denekamp.svg']],
                        ['title' => $this->t('Fassade — Industriebau', 'Façade — industrial building'), 'text' => '', '__files' => ['image' => 'ref-facade.svg']],
                    ],
                ],
            ],
            // 9 — Transparenz (zentrierter Textblock)
            [
                'ctype' => 'heliatek_text',
                'fields' => [
                    'headline' => $this->t('Nachhaltig — mit offenen Karten.', 'Sustainable — with an open hand.'),
                    'bodytext' => $this->t(
                        '<p>HeliaSol v2 bleibt eine der nachhaltigeren Solartechnologien am Markt — auch wenn Perowskit-Zellen nicht ganz an den CO₂-Fußabdruck unserer organischen Vorgängertechnologie heranreichen. Genaue Vergleichswerte veröffentlichen wir mit dem finalen Datenblatt. Das ist unser Anspruch: keine Greenwashing-Formeln, sondern messbare Zahlen.</p>',
                        '<p>HeliaSol v2 remains one of the more sustainable solar technologies on the market — even if perovskite cells don’t quite match the carbon footprint of our organic predecessor. We will publish exact comparison figures with the final datasheet. That’s our standard: no greenwashing formulas, just measurable numbers.</p>'
                    ),
                    'align' => 'center',
                    'background' => 'alt',
                ],
            ],
            // 10 — FAQ (Akkordeon)
            [
                'ctype' => 'heliatek_accordion',
                'fields' => ['headline' => $this->t('Häufige Fragen', 'Frequently asked questions')],
                'collections' => [
                    'accordion_items' => [
                        ['question' => $this->t('Was bedeutet „Arbeitstitel"?', 'What does “working title” mean?'), 'answer' => $this->t('<p>„HeliaSol v2" ist der interne Projektname der neuen Perowskit-Generation. Der finale Produktname wird mit der Markteinführung kommuniziert.</p>', '<p>“HeliaSol v2” is the internal project name of the new perovskite generation. The final product name will be announced at market launch.</p>')],
                        ['question' => $this->t('Wann ist das Produkt verfügbar?', 'When will the product be available?'), 'answer' => $this->t('<p>Den Zeitplan zur Markteinführung geben wir bekannt, sobald Zertifizierung und Serienfertigung gesichert sind. Registrieren Sie sich über das Kontaktformular für Updates.</p>', '<p>We will announce the market-launch timeline once certification and volume production are secured. Register via the contact form for updates.</p>')],
                        ['question' => $this->t('Ist die neue Generation so nachhaltig wie HeliaSol?', 'Is the new generation as sustainable as HeliaSol?'), 'answer' => $this->t('<p>Sie bleibt eine der nachhaltigeren Solartechnologien am Markt, erreicht aber nicht ganz den CO₂-Fußabdruck der organischen Vorgängertechnologie. Genaue Werte veröffentlichen wir mit dem finalen Datenblatt.</p>', '<p>It remains one of the more sustainable solar technologies on the market, but does not quite reach the carbon footprint of the organic predecessor. Exact figures will follow with the final datasheet.</p>')],
                        ['question' => $this->t('Bleibt die Montage gleich?', 'Does mounting stay the same?'), 'answer' => $this->t('<p>Ja. Die Module sind weiterhin ultra-leicht, flexibel und rückseitig selbstklebend — die bewährte, werkzeugarme Installation bleibt erhalten.</p>', '<p>Yes. The modules remain ultra-light, flexible and self-adhesive on the back — the proven, low-tooling installation stays the same.</p>')],
                        ['question' => $this->t('Was passiert mit HeliaSol (OPV)?', 'What happens to HeliaSol (OPV)?'), 'answer' => $this->t('<p>Bestehende Installationen und Garantien bleiben unberührt. Informationen zum weiteren Produktangebot folgen mit der Markteinführung der neuen Generation.</p>', '<p>Existing installations and warranties remain unaffected. Information on the future product range will follow with the launch of the new generation.</p>')],
                    ],
                ],
            ],
            // 11 — CTA (Mint)
            [
                'ctype' => 'heliatek_cta',
                'fields' => [
                    'headline' => $this->t('Sprechen wir über die nächste Generation!', 'Let’s talk about the next generation!'),
                    'text' => '',
                ],
                'collections' => [
                    'cta_buttons' => [
                        ['label' => $this->t('Zum Kontaktformular', 'To the contact form'), 'link' => '#kontakt', 'style' => 'primary'],
                    ],
                ],
            ],
        ];
    }
}
