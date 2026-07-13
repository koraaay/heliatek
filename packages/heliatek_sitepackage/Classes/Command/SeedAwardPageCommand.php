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
 * Legt die Beispiel-Landingpage "HeliaSol v2" (Award-Design) mit allen
 * Sektions-Komponenten als echte TYPO3-Seite an.
 *
 * Aufruf:  vendor/bin/typo3 heliatek:seed:award-page
 * Die Bilder werden aus fileadmin/heliatek/ referenziert (SVG-Platzhalter,
 * die Redakteure später durch echte Fotos ersetzen).
 */
#[AsCommand(
    name: 'heliatek:seed:award-page',
    description: 'Legt die HeliaSol-v2-Landingpage (Award-Design) als TYPO3-Seite an.'
)]
final class SeedAwardPageCommand extends Command
{
    private int $newCounter = 0;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        Bootstrap::initializeBackendAuthentication();
        $backendUser = GeneralUtility::makeInstance(CommandLineUserAuthentication::class);
        $backendUser->authenticate();
        $GLOBALS['BE_USER'] = $backendUser;
        $GLOBALS['BE_USER']->workspace = 0;

        $this->copyDemoAssets($io);

        $pageUid = $this->ensureRootPage($io);
        if ($pageUid === 0) {
            $io->error('Root-Seite konnte nicht angelegt werden.');
            return Command::FAILURE;
        }
        $io->writeln('Root-Seite: uid ' . $pageUid);

        $this->clearContent($pageUid);
        $io->writeln('Bestehende Inhalte entfernt.');

        foreach ($this->sections() as $i => $section) {
            $this->createElement($pageUid, $i + 1, $section, $io);
        }

        GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->flushCaches();
        $io->success('Award-Seite "HeliaSol v2" aufgebaut. Frontend: "/" (rootPageId 1).');
        return Command::SUCCESS;
    }

    private function newId(string $prefix): string
    {
        return 'NEW_' . $prefix . '_' . (++$this->newCounter);
    }

    /**
     * Kopiert die mitgelieferten Demo-Bilder (SVG-Platzhalter) nach
     * fileadmin/heliatek/, damit die Seite ohne manuellen Upload rendert.
     */
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

    private function clearContent(int $pageUid): void
    {
        $pool = GeneralUtility::makeInstance(ConnectionPool::class);
        // Kind-Datensätze der bestehenden Inhaltselemente entfernen
        $contentUids = $pool->getConnectionForTable('tt_content')
            ->select(['uid'], 'tt_content', ['pid' => $pageUid])
            ->fetchFirstColumn();
        $collectionTables = [
            'hero_buttons', 'feature_items', 'stat_items', 'compare_bars',
            'spec_items', 'card_items', 'accordion_items', 'cta_buttons', 'compare_cards',
        ];
        foreach ($collectionTables as $table) {
            $pool->getConnectionForTable($table)->delete($table, ['pid' => $pageUid]);
        }
        $pool->getConnectionForTable('sys_file_reference')->delete('sys_file_reference', ['pid' => $pageUid]);
        $pool->getConnectionForTable('tt_content')->delete('tt_content', ['pid' => $pageUid]);
        unset($contentUids);
    }

    private function fileUid(string $path): int
    {
        $factory = GeneralUtility::makeInstance(ResourceFactory::class);
        $file = $factory->getFileObjectFromCombinedIdentifier('1:/heliatek/' . $path);
        return $file->getUid();
    }

    private function base(int $pageUid): array
    {
        $now = time();
        return [
            'pid' => $pageUid,
            'tstamp' => $now,
            'crdate' => $now,
            'deleted' => 0,
            'hidden' => 0,
            'sys_language_uid' => 0,
        ];
    }

    private function addFileReference(int $pageUid, string $table, string $field, int $foreignUid, int $fileUid, int $sorting): void
    {
        $conn = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_file_reference');
        $conn->insert('sys_file_reference', array_merge($this->base($pageUid), [
            'uid_local' => $fileUid,
            'tablenames' => $table,
            'fieldname' => $field,
            'uid_foreign' => $foreignUid,
            'sorting_foreign' => $sorting,
        ]));
    }

    /**
     * @param array $section [ctype, fields[], collections[table=>[rows]], files[field=>path]]
     */
    private function createElement(int $pageUid, int $sorting, array $section, SymfonyStyle $io): void
    {
        $pool = GeneralUtility::makeInstance(ConnectionPool::class);
        $ttConn = $pool->getConnectionForTable('tt_content');

        $row = array_merge($this->base($pageUid), [
            'CType' => $section['ctype'],
            'colPos' => 0,
            'sorting' => $sorting * 256,
        ], $section['fields'] ?? []);

        // Zählerfelder für Collections und Datei-Referenzen setzen
        foreach (($section['collections'] ?? []) as $table => $rows) {
            $row[$table] = count($rows);
        }
        foreach (($section['files'] ?? []) as $field => $path) {
            $row[$field] = 1;
        }

        $ttConn->insert('tt_content', $row);
        $parentUid = (int)$ttConn->lastInsertId();

        // Collections mit explizitem Eltern-Zeiger
        foreach (($section['collections'] ?? []) as $table => $rows) {
            $conn = $pool->getConnectionForTable($table);
            $pos = 1;
            foreach ($rows as $childRow) {
                $files = $childRow['__files'] ?? [];
                unset($childRow['__files']);
                // Zählerfeld für Datei-Felder der Collection
                foreach ($files as $field => $path) {
                    $childRow[$field] = 1;
                }
                $conn->insert($table, array_merge($this->base($pageUid), [
                    'foreign_table_parent_uid' => $parentUid,
                    'sorting' => $pos * 256,
                ], $childRow));
                $childUid = (int)$conn->lastInsertId();
                foreach ($files as $field => $path) {
                    $this->addFileReference($pageUid, $table, $field, $childUid, $this->fileUid($path), 1);
                }
                $pos++;
            }
        }

        // Datei-Felder direkt am Element
        $fpos = 1;
        foreach (($section['files'] ?? []) as $field => $path) {
            $this->addFileReference($pageUid, 'tt_content', $field, $parentUid, $this->fileUid($path), $fpos++);
        }

        $io->writeln('  ✓ ' . $section['ctype'] . ' (uid ' . $parentUid . ')');
    }

    /**
     * Inhalt der Award-Seite, Sektion für Sektion.
     */
    private function sections(): array
    {
        return [
            // 1 — Hero (dunkel)
            [
                'ctype' => 'heliatek_hero',
                'fields' => [
                    'badge' => 'Neu · Arbeitstitel HeliaSol v2',
                    'headline' => 'Die nächste Generation der Solarfolie.',
                    'teaser' => 'HeliaSol v2 — weltweit erstes flexibles, perowskitbasiertes Solarmodul. Entwickelt und gefertigt in Dresden. Gleiche Haltung, neue Technologie.',
                ],
                'collections' => [
                    'hero_buttons' => [
                        ['label' => 'Jetzt informieren', 'link' => '#kontakt', 'style' => 'primary'],
                        ['label' => 'Technologie-Vergleich', 'link' => '#vergleich', 'style' => 'secondary'],
                    ],
                ],
            ],
            // 2 — Produktbild (Konzept)
            [
                'ctype' => 'heliatek_image',
                'fields' => [
                    'annotation' => 'Konzeptbild — echtes Produktfoto folgt',
                    'aspect' => '16/9',
                ],
                'files' => ['image' => 'product-mockup-grey.svg'],
            ],
            // 3 — Was bleibt (USP-Grid)
            [
                'ctype' => 'heliatek_featuregrid',
                'fields' => [
                    'headline' => 'Die Heliatek-DNA',
                    'intro' => 'Alles, was HeliaSol ausmacht, trägt auch die nächste Generation: ultra-leicht, flexibel, selbstklebend — und einfach zu installieren, wo herkömmliche Paneele scheitern.',
                ],
                'collections' => [
                    'feature_items' => [
                        ['title' => 'Ultra-Leicht', '__files' => ['icon' => 'usp-lightweight.svg']],
                        ['title' => 'Flexibel', '__files' => ['icon' => 'usp-flexibility.svg']],
                        ['title' => 'Wirklich Grün', '__files' => ['icon' => 'usp-truly-green.svg']],
                        ['title' => 'Einfache Installation', '__files' => ['icon' => 'usp-easy-install.svg']],
                        ['title' => 'Temperatur-unabhängig', '__files' => ['icon' => 'usp-temperature.svg']],
                        ['title' => 'Made in Dresden'],
                    ],
                ],
            ],
            // 4 — Vorher/Nachher-Slider (dunkel)
            [
                'ctype' => 'heliatek_imagecompare',
                'fields' => [
                    'before_label' => 'Bisher — HeliaSol (OPV)',
                    'after_label' => 'Neu — HeliaSol v2 (Perowskit)',
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
                    'headline' => 'Von Organisch zu Perowskit',
                    'intro' => 'Links die bewährte organische Solarfolie, rechts die neue Perowskit-Generation.',
                    'dark_background' => 1,
                    'bars_label' => 'Effizienz im Vergleich (Richtwert — finale Zahlen folgen mit dem Datenblatt)',
                ],
                'collections' => [
                    'compare_bars' => [
                        ['label' => 'HeliaSol (OPV)', 'bar_value' => '1×', 'percent' => 38, 'bar_color' => '#8b5fbf', 'emphasized' => 0],
                        ['label' => 'HeliaSol v2 (Perowskit)', 'bar_value' => '~2×', 'percent' => 76, 'bar_color' => '#8a8d88', 'emphasized' => 1],
                    ],
                    'spec_items' => [
                        ['spec_label' => 'Zelltechnologie', 'spec_value' => 'Perowskit statt Organisch'],
                        ['spec_label' => 'Erscheinung', 'spec_value' => 'Matt-Grau statt Violett-changierend'],
                        ['spec_label' => 'Herstellung', 'spec_value' => 'Weiterhin Dresden, Deutschland'],
                        ['spec_label' => 'Montage', 'spec_value' => 'Weiterhin selbstklebend, werkzeugarm'],
                    ],
                ],
            ],
            // 6 — Kennzahlen (Mint-Band)
            [
                'ctype' => 'heliatek_stats',
                'fields' => [
                    'headline' => 'Kennzahlen auf einen Blick',
                    'dark_background' => 0,
                    'footnote' => 'Vorläufige Ziel- und Richtwerte. Verbindliche Spezifikationen veröffentlichen wir mit dem finalen Datenblatt zur Markteinführung.',
                ],
                'collections' => [
                    'stat_items' => [
                        ['value' => '~2×', 'label' => 'Effizienz ggü. OPV (Richtwert)'],
                        ['value' => '<2 kg', 'label' => 'Gewicht pro Modul (Ziel)'],
                        ['value' => '≈2 mm', 'label' => 'Dicke ohne Anschlussdose (Ziel)'],
                        ['value' => '50 cm', 'label' => 'Min. Biegeradius'],
                    ],
                ],
            ],
            // 7 — Technologie / Schichtaufbau (Text & Bild)
            [
                'ctype' => 'heliatek_textmedia',
                'fields' => [
                    'headline' => 'Folie bleibt Folie.',
                    'bodytext' => '<p>Der bewährte Schichtaufbau — aktive Zellschicht zwischen Barrierefolien, rückseitig vollflächig klebend — bleibt erhalten. Neu ist die aktive Schicht: Perowskit-Zellen ersetzen die organischen Zellen und liefern deutlich mehr Energie pro Fläche.</p>',
                    'media_position' => 'right',
                    'background' => 'alt',
                ],
                'files' => ['image' => 'schichtaufbau.svg'],
            ],
            // 8 — Referenzen (Karten)
            [
                'ctype' => 'heliatek_cardgrid',
                'fields' => [
                    'headline' => 'Überall dort, wo Paneele scheitern',
                    'intro' => 'Bewährte HeliaSol-Installationen zeigen die Einsatzfelder: Leichtbaudächer, Fassaden, gebogene Flächen — HeliaSol v2 übernimmt sie mit fast doppelter Energieausbeute.',
                ],
                'collections' => [
                    'card_items' => [
                        ['title' => 'Leichtbaudach — Barcelona', 'text' => '', '__files' => ['image' => 'ref-barcelona.svg']],
                        ['title' => 'Gebogene Fläche — Denekamp', 'text' => '', '__files' => ['image' => 'ref-denekamp.svg']],
                        ['title' => 'Fassade — Industriebau', 'text' => '', '__files' => ['image' => 'ref-facade.svg']],
                    ],
                ],
            ],
            // 9 — Transparenz (zentrierter Textblock)
            [
                'ctype' => 'heliatek_text',
                'fields' => [
                    'headline' => 'Nachhaltig — mit offenen Karten.',
                    'bodytext' => '<p>HeliaSol v2 bleibt eine der nachhaltigeren Solartechnologien am Markt — auch wenn Perowskit-Zellen nicht ganz an den CO₂-Fußabdruck unserer organischen Vorgängertechnologie heranreichen. Genaue Vergleichswerte veröffentlichen wir mit dem finalen Datenblatt. Das ist unser Anspruch: keine Greenwashing-Formeln, sondern messbare Zahlen.</p>',
                    'align' => 'center',
                    'background' => 'alt',
                ],
            ],
            // 10 — FAQ (Akkordeon)
            [
                'ctype' => 'heliatek_accordion',
                'fields' => ['headline' => 'Häufige Fragen'],
                'collections' => [
                    'accordion_items' => [
                        ['question' => 'Was bedeutet „Arbeitstitel"?', 'answer' => '<p>„HeliaSol v2" ist der interne Projektname der neuen Perowskit-Generation. Der finale Produktname wird mit der Markteinführung kommuniziert.</p>'],
                        ['question' => 'Wann ist das Produkt verfügbar?', 'answer' => '<p>Den Zeitplan zur Markteinführung geben wir bekannt, sobald Zertifizierung und Serienfertigung gesichert sind. Registrieren Sie sich über das Kontaktformular für Updates.</p>'],
                        ['question' => 'Ist die neue Generation so nachhaltig wie HeliaSol?', 'answer' => '<p>Sie bleibt eine der nachhaltigeren Solartechnologien am Markt, erreicht aber nicht ganz den CO₂-Fußabdruck der organischen Vorgängertechnologie. Genaue Werte veröffentlichen wir mit dem finalen Datenblatt.</p>'],
                        ['question' => 'Bleibt die Montage gleich?', 'answer' => '<p>Ja. Die Module sind weiterhin ultra-leicht, flexibel und rückseitig selbstklebend — die bewährte, werkzeugarme Installation bleibt erhalten.</p>'],
                        ['question' => 'Was passiert mit HeliaSol (OPV)?', 'answer' => '<p>Bestehende Installationen und Garantien bleiben unberührt. Informationen zum weiteren Produktangebot folgen mit der Markteinführung der neuen Generation.</p>'],
                    ],
                ],
            ],
            // 11 — CTA (Mint)
            [
                'ctype' => 'heliatek_cta',
                'fields' => [
                    'headline' => 'Sprechen wir über die nächste Generation!',
                    'text' => '',
                ],
                'collections' => [
                    'cta_buttons' => [
                        ['label' => 'Zum Kontaktformular', 'link' => '#kontakt', 'style' => 'primary'],
                    ],
                ],
            ],
        ];
    }
}
