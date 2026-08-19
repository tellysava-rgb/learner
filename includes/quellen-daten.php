<?php
/**
 * Zentrales Quellenverzeichnis für den Bereich "Wissenschaftlich Sprachen lernen"
 * (infos/wissen.php, infos/mythen.php, infos/studien.php, infos/skala.php,
 * infos/lernplan.php, infos/podcasts.php).
 *
 * Jede Quelle wurde einzeln per Websuche/Volltext-Abgleich gegen die Originalpublikation
 * geprüft (Autor, Jahr, Journal, DOI, wo möglich Kennzahlen wie Stichprobengrösse/Effektstärke).
 * status:
 *   'verifiziert'   = Autor/Jahr/Journal/DOI + genannte Kennzahlen bestätigt
 *   'identifiziert' = Autor/Jahr/Journal/DOI eindeutig gefunden, Kennzahlen nicht einzeln
 *                     nachgeprüft (Paywall)
 *   'unklar'        = Themengebiet real, aber keine einzelne Studie zweifelsfrei als DIE
 *                      Quelle identifizierbar — wird entsprechend vorsichtig referenziert
 *
 * Vollständige Recherche-Dokumentation mit Belegen: siehe Projekt-Notiz "quellen.md".
 */

$QUELLEN = [

    'kim-webb-spacing' => [
        'autor' => 'Kim, D., & Webb, S. (2022)',
        'titel' => 'The Effects of Spaced Practice on Second Language Learning: A Meta-Analysis',
        'journal' => 'Language Learning, 72(4)',
        'doi' => 'https://onlinelibrary.wiley.com/doi/abs/10.1111/lang.12479',
        'kennzahl' => '98 Effektstärken aus 48 Experimenten — klarer Vorteil verteilten gegenüber massiertem Lernen',
        'status' => 'identifiziert',
    ],
    'webb-uchihara-yanagisawa-incidental' => [
        'autor' => 'Webb, S., Uchihara, T., & Yanagisawa, A. (2023)',
        'titel' => 'How effective is second language incidental vocabulary learning? A meta-analysis',
        'journal' => 'Language Teaching, 56(2)',
        'doi' => 'https://www.cambridge.org/core/journals/language-teaching/article/how-effective-is-second-language-incidental-vocabulary-learning-a-metaanalysis/E38E3468FD2090B1FA3051051DE8E70C',
        'kennzahl' => '24 Studien, N=2.771 — beiläufiges Lernen aus Lesen/Hören: 9–18 % unmittelbar, 6–17 % verzögert',
        'status' => 'verifiziert',
    ],
    'webb-yanagisawa-intentional' => [
        'autor' => 'Webb, S., & Yanagisawa, A. (2020)',
        'titel' => 'How Effective Are Intentional Vocabulary-Learning Activities? A Meta-Analysis',
        'journal' => 'The Modern Language Journal, 105(2)',
        'doi' => 'https://onlinelibrary.wiley.com/doi/abs/10.1111/modl.12671',
        'kennzahl' => '22 Studien — gezieltes Lernen (Karteikarten etc.): ca. 60 % unmittelbare Behaltensleistung',
        'status' => 'identifiziert',
    ],
    'vos-spoken-input' => [
        'autor' => 'Vos, S., Marinus, E., & de Bree, E. (2018)',
        'titel' => 'A Meta-Analysis and Meta-Regression of Incidental Second Language Word Learning from Spoken Input',
        'journal' => 'Language Learning, 68(4)',
        'doi' => 'https://onlinelibrary.wiley.com/doi/10.1111/lang.12296',
        'kennzahl' => 'gesprochener, bedeutungsorientierter Input: g≈1,05; interaktiv > nicht-interaktiv',
        'status' => 'identifiziert',
    ],
    'kim-lee-lee-l1l2gloss' => [
        'autor' => 'Kim, H. S., Lee, J. H., & Lee, H. (2020/2024)',
        'titel' => 'The relative effects of L1 and L2 glosses on L2 learning: A meta-analysis',
        'journal' => 'Language Teaching Research, 28(1)',
        'doi' => 'https://journals.sagepub.com/doi/full/10.1177/1362168820981394',
        'kennzahl' => '26 Studien, N=2.189 — L1-Erklärungen wirksamer als L2-Glosses (g=.33), bei Anfängern g≈.80',
        'status' => 'verifiziert',
    ],
    'yi-zhong-multiword' => [
        'autor' => 'Yi, W., & Zhong, Y. (2023/2024)',
        'titel' => 'The processing advantage of multiword sequences: A meta-analysis',
        'journal' => 'Studies in Second Language Acquisition',
        'doi' => 'https://www.cambridge.org/core/journals/studies-in-second-language-acquisition/article/abs/processing-advantage-of-multiword-sequences-a-metaanalysis/64D9BFF2B458422C8CCE202A520F914A',
        'kennzahl' => '35 Studien, 130 Effektstärken, N=1.981 — kleiner bis mittlerer Verarbeitungsvorteil bekannter Chunks',
        'status' => 'verifiziert',
    ],
    'yu-trainin-tech-vocab' => [
        'autor' => 'Yu, A., & Trainin, G. (2021/2022)',
        'titel' => 'A meta-analysis examining technology-assisted L2 vocabulary learning',
        'journal' => 'ReCALL, 34(2)',
        'doi' => 'https://www.cambridge.org/core/journals/recall/article/metaanalysis-examining-technologyassisted-l2-vocabulary-learning/08A549A6CFD1078406E6A4F8AFE28184',
        'kennzahl' => '34 Studien, N=2.511 — d=.64 gesamt, nur d=.30 für K–12',
        'status' => 'verifiziert',
    ],
    'tonzar-picture-translation' => [
        'autor' => 'Tonzar, C., Lotto, L., & Job, R. (2009)',
        'titel' => 'L2 Vocabulary Acquisition in Children: Effects of Learning Method and Cognate Status',
        'journal' => 'Language Learning, 59(3), 623–646',
        'doi' => 'https://eric.ed.gov/?id=EJ850413',
        'kennzahl' => 'Bildmethode > Übersetzungsmethode bei Kindern; Kognaten-Vorteil schwächt mit steigendem Niveau',
        'status' => 'verifiziert',
    ],
    'oppici-gestures' => [
        'autor' => 'Oppici, L., Mathias, B., Narciss, S., & Proske, A. (2023)',
        'titel' => 'Benefits of Enacting and Observing Gestures on Foreign Language Vocabulary Learning: A Systematic Review and Meta-Analysis',
        'journal' => 'Behavioral Sciences, 13(11), 920',
        'doi' => 'https://doi.org/10.3390/bs13110920',
        'kennzahl' => '7 Studien, N=309 — selbst ausgeführte Gesten ≈ nur beobachtete Gesten',
        'status' => 'verifiziert',
    ],

    'sangers-extensive-reading' => [
        'autor' => 'Sangers, N. L., van der Sande, L., Welie, C., Dobber, M., & van Steensel, R. (2025)',
        'titel' => 'Learning a Language Through Reading: A Meta-analysis of Studies on the Effects of Extensive Reading on Second and Foreign Language Learning',
        'journal' => 'Educational Psychology Review, 37, Art. 96',
        'doi' => 'https://link.springer.com/article/10.1007/s10648-025-10068-6',
        'kennzahl' => '73 Studien, 82 Interventionen — d≈.38–.41, grösser mit passendem Niveau + Anschlussaktivität',
        'status' => 'verifiziert',
    ],
    'nakanishi-extensive-reading' => [
        'autor' => 'Nakanishi, T. (2015)',
        'titel' => 'A Meta-Analysis of Extensive Reading Research',
        'journal' => 'TESOL Quarterly, 49(1)',
        'doi' => 'https://onlinelibrary.wiley.com/doi/10.1002/tesq.157',
        'kennzahl' => '34 Studien, N=3.942 — d=.46 in kontrollierten Gruppenvergleichen',
        'status' => 'identifiziert',
    ],
    'odo-phonics' => [
        'autor' => 'Odo, D. M. (2021)',
        'titel' => 'A Meta-Analysis of the Effect of Phonological Awareness and/or Phonics Instruction on Word and Pseudo Word Reading of English as an L2',
        'journal' => 'SAGE Open',
        'doi' => 'https://journals.sagepub.com/doi/full/10.1177/21582440211059168',
        'kennzahl' => 'g=.53 gesamt für L2-Wortlesen, g=.43 Primarschul-Subgruppe',
        'status' => 'verifiziert',
    ],

    'zhang-zhang-vocab-comprehension' => [
        'autor' => 'Zhang, S., & Zhang, X. (2022)',
        'titel' => 'The relationship between vocabulary knowledge and L2 reading/listening comprehension: A meta-analysis',
        'journal' => 'Language Teaching Research, 26(4)',
        'doi' => 'https://journals.sagepub.com/doi/abs/10.1177/1362168820913998',
        'kennzahl' => '>100 Studien, N≈21.000 — r=.56 (Hören) / r=.57 (Lesen)',
        'status' => 'verifiziert',
    ],
    'dalman-plonsky-listening-strategy' => [
        'autor' => 'Dalman, M., & Plonsky, L. (2022/2025)',
        'titel' => 'The effectiveness of second-language listening strategy instruction: A meta-analysis',
        'journal' => 'Language Teaching Research',
        'doi' => 'https://journals.sagepub.com/doi/abs/10.1177/13621688211072981',
        'kennzahl' => '45 Primärstudien, 51 Stichproben — d=.69',
        'status' => 'verifiziert',
    ],
    'sutton-webb-audiovisual' => [
        'autor' => 'Sutton, D., & Webb, S. (2026)',
        'titel' => 'The effects of audiovisual input on second language learning: A meta-analysis',
        'journal' => 'Studies in Second Language Acquisition, 48(2)',
        'doi' => 'https://www.cambridge.org/core/journals/studies-in-second-language-acquisition/article/effects-of-audiovisual-input-on-second-language-learning-a-metaanalysis/9B61BAEF14F110F01148E398D171634A',
        'kennzahl' => '56 Experimente, N=1.954 — g=.89 (Pre-Post ohne Kontrollgruppe); lehrorientiert > unterhaltungsorientiert',
        'status' => 'verifiziert',
    ],
    'zuest-sleep' => [
        'autor' => 'Züst, M. A., Ruch, S., Wiest, R., & Henke, K. (2019)',
        'titel' => 'Implicit vocabulary learning during sleep is bound to slow-wave peaks',
        'journal' => 'Current Biology, 29(4)',
        'doi' => 'https://doi.org/10.1016/j.cub.2018.12.038',
        'kennzahl' => 'Lernen nur bei präzisem EEG-Timing auf Tiefschlaf-Up-States — kein Beleg für „Podcast über Nacht laufen lassen"',
        'status' => 'verifiziert',
    ],

    'mackey-goo-interaction' => [
        'autor' => 'Mackey, A., & Goo, J. (2007)',
        'titel' => 'Interaction research in SLA: A meta-analysis and research synthesis',
        'journal' => 'In A. Mackey (Ed.), Conversational Interaction in SLA, Oxford University Press',
        'doi' => 'https://www.cambridge.org/core/journals/language-teaching/article/interaction-and-instructed-second-language-acquisition/78A156EE200F744F5978F99BFB073DBE',
        'kennzahl' => '28 Interaktionsstudien — grosser Vorteil von Interaktion, v.a. auf verzögerten Tests',
        'status' => 'identifiziert',
    ],
    'lyster-saito-feedback' => [
        'autor' => 'Lyster, R., & Saito, K. (2010)',
        'titel' => 'Oral feedback in classroom SLA: A meta-analysis',
        'journal' => 'Studies in Second Language Acquisition, 32(2)',
        'doi' => 'https://www.cambridge.org/core/journals/studies-in-second-language-acquisition/article/abs/oral-feedback-in-classroom-sla/4999EE1C8379B2BF026B148EAF373CA1',
        'kennzahl' => '15 Studien, N=827 — Prompts wirksamer als reine Recasts',
        'status' => 'verifiziert',
    ],
    'hou-min-ai-dialogue' => [
        'autor' => 'Hou, Z., & Min, S. (2025/2026)',
        'titel' => 'Dialogue-based computer-assisted language learning systems for second language speaking development: A three-level meta-analysis',
        'journal' => 'ReCALL, 38(1)',
        'doi' => 'https://www.cambridge.org/core/journals/recall/article/dialoguebased-computerassisted-language-learning-systems-for-second-language-speaking-development-a-threelevel-metaanalysis/31847710516602398819C5E594038E7B',
        'kennzahl' => '16 Studien, 89 Effektstärken — g=.61 für L2-Sprechen durch Dialogsysteme',
        'status' => 'verifiziert',
    ],
    'brown-liu-norouzian-wcf' => [
        'autor' => 'Brown, D., Liu, Q., & Norouzian, R. (2023)',
        'titel' => 'Effectiveness of written corrective feedback in developing L2 accuracy: A Bayesian meta-analysis',
        'journal' => 'Language Teaching Research, 30(3)',
        'doi' => 'https://journals.sagepub.com/doi/abs/10.1177/13621688221147374',
        'kennzahl' => '52 Studien — direkte/indirekte/metalinguistische Korrektur ähnlich effektiv, moderate anhaltende Wirkung',
        'status' => 'verifiziert',
    ],

    'kang-sok-han-formfocus' => [
        'autor' => 'Kang, E. Y., Sok, S., & Han, Z. (2019)',
        'titel' => 'Thirty-five years of ISLA on form-focused instruction: A meta-analysis',
        'journal' => 'Language Teaching Research, 23(4)',
        'doi' => 'https://journals.sagepub.com/doi/10.1177/1362168818776671',
        'kennzahl' => '54 Studien, N=5.051 — g=1.06, 95%-KI [0.84–1.29]',
        'status' => 'verifiziert',
    ],
    'spada-tomita-explicit' => [
        'autor' => 'Spada, N., & Tomita, Y. (2010)',
        'titel' => 'Interactions between Type of Instruction and Type of Language Feature: A Meta-Analysis',
        'journal' => 'Language Learning, 60(2)',
        'doi' => 'https://onlinelibrary.wiley.com/doi/10.1111/j.1467-9922.2010.00562.x',
        'kennzahl' => 'explizite Instruktion tendenziell wirksamer als implizite, bei einfachen wie komplexen Strukturen',
        'status' => 'unklar',
    ],

    'lee-jang-plonsky-pronunciation' => [
        'autor' => 'Lee, J., Jang, J., & Plonsky, L. (2015)',
        'titel' => 'The Effectiveness of Second Language Pronunciation Instruction: A Meta-Analysis',
        'journal' => 'Applied Linguistics, 36(3), 345–366',
        'doi' => 'https://academic.oup.com/applij/article/36/3/345/2422438',
        'kennzahl' => '86 Reports — d=.80 (between-group) / d=.89 (within-group)',
        'status' => 'verifiziert',
    ],
    'yao-he-phonetic-training' => [
        'autor' => 'Yao, Y., He, X., Chen, Y., & Zhu, Y. (2025)',
        'titel' => 'A Meta-Analysis of Second Language Phonetic Training: Exploring Overall Effect and Moderating Factors',
        'journal' => 'Journal of Speech, Language, and Hearing Research',
        'doi' => 'https://pubmed.ncbi.nlm.nih.gov/40106429/',
        'kennzahl' => '65 Studien, N=2.793 — d=.762; Wahrnehmungstraining am wirksamsten',
        'status' => 'verifiziert',
    ],
    'uchihara-karas-thomson-hvpt' => [
        'autor' => 'Uchihara, T., Karas, M., & Thomson, R. (2025)',
        'titel' => 'High variability phonetic training (HVPT): A meta-analysis of L2 perceptual training studies',
        'journal' => 'Studies in Second Language Acquisition, 47(3)',
        'doi' => 'https://www.cambridge.org/core/journals/studies-in-second-language-acquisition/article/high-variability-phonetic-training-hvpt-a-metaanalysis-of-l2-perceptual-training-studies/6ABB8C1F32D88D53EA8D05A4565E76F6',
        'kennzahl' => 'g=.67 gegenüber Kontrollbedingungen; teilweise Generalisierung auf neue Stimmen/Stimuli',
        'status' => 'verifiziert',
    ],

    'tseng-liu-hsu-chu-studyabroad' => [
        'autor' => 'Tseng, W.-T., Liu, Y.-T., Hsu, Y.-T., & Chu, H.-C. (2021/2024)',
        'titel' => 'Revisiting the effectiveness of study abroad language programs: A multi-level meta-analysis',
        'journal' => 'Language Teaching Research, 28(1)',
        'doi' => 'https://journals.sagepub.com/doi/full/10.1177/1362168820988423',
        'kennzahl' => '42 Studien, 283 Effektstärken — g=.87',
        'status' => 'verifiziert',
    ],
    'clil-primary-unclear' => [
        'autor' => 'Vermutlich: Studie zu CLIL/Immersion in der Primarschule (mehrere Arbeiten 2019–2025)',
        'titel' => 'Effects of content and language integrated learning at the primary school level: A multi-level meta-analysis',
        'journal' => '(Verlagsseite blockierte Volltext-Zugriff — Kennzahlen nicht abschliessend bestätigt)',
        'doi' => 'https://www.sciencedirect.com/science/article/abs/pii/S1747938X2500003X',
        'kennzahl' => 'im Ausgangsdokument genannt: 21 Studien, 28 Stichproben, d=.63 gesamt / d=1.24 Sprechen',
        'status' => 'unklar',
    ],

    'schulz-multiword-review' => [
        'autor' => 'Schulz, J., Hamilton, C., Wonnacott, E., & Murphy, V. (2023)',
        'titel' => 'The impact of multi-word units in early foreign language learning and teaching contexts: A systematic review',
        'journal' => 'Review of Education, 11(2)',
        'doi' => 'https://bera-journals.onlinelibrary.wiley.com/doi/10.1002/rev3.3413',
        'kennzahl' => '2.233 Treffer gescreent, nur 2 Studien erfüllten Einschlusskriterien — keine belastbare Wirksamkeitsaussage für Kinder möglich',
        'status' => 'verifiziert',
    ],
    'shared-book-reading-unclear' => [
        'autor' => 'Vermutlich: aktuelle Meta-Analyse zu Shared Book Reading bei ESL/EFL-Kindern (2025)',
        'titel' => 'The effects of shared book reading on language and literacy development of ESL/EFL learners: a multi-level meta-analysis',
        'journal' => 'European Early Childhood Education Research Journal (Zugriff mehrfach blockiert)',
        'doi' => 'https://www.tandfonline.com/doi/full/10.1080/1350293X.2025.2514768',
        'kennzahl' => 'im Ausgangsdokument genannt: 13 Studien, N=1.857 — g=.49 kurzfristig, g=.20 langfristig (n.s.)',
        'status' => 'unklar',
    ],
    'liu-zhang-dai-mobilegames' => [
        'autor' => 'Liu, Y., Zhang, Q., & Dai, Y. (2025)',
        'titel' => 'Do mobile games improve language learning? A meta-analysis',
        'journal' => 'Computer Assisted Language Learning',
        'doi' => 'https://www.tandfonline.com/doi/full/10.1080/09588221.2025.2528786',
        'kennzahl' => '38 quasi-experimentelle Studien, N=4.102 — g=.962, 95%-KI [0.688–1.235], hohe Heterogenität',
        'status' => 'verifiziert',
    ],

    'birkenbihl-note' => [
        'autor' => '—',
        'titel' => 'Birkenbihl-Methode: keine belastbare Wirksamkeitsstudie auffindbar',
        'journal' => 'Recherche-Befund, kein Zitat',
        'doi' => '',
        'kennzahl' => 'Weder Meta-Analyse noch kontrollierte Interventionsstudie zum Gesamtpaket der Methode gefunden — einzelne Bestandteile sind mit etablierten Methoden kompatibel',
        'status' => 'unklar',
    ],
    'fruehbeginn-note' => [
        'autor' => '—',
        'titel' => 'Früher Beginn Fremdsprachenunterricht (deutscher Schulkontext): kontrovers diskutierte Befundlage',
        'journal' => 'Mehrere Studien mit gegensätzlichen Befunden (u.a. Jäkel/Ritter 2017 vs. Gegendarstellungen)',
        'doi' => '',
        'kennzahl' => 'kein Einzelbefund als „die" Quelle zitierfähig — bewusst vorsichtig formuliert statt Scheingenauigkeit',
        'status' => 'unklar',
    ],
];

/**
 * Rendert eine ein-/ausklappbare Quellenliste (Bootstrap-Accordion, standardmässig zugeklappt)
 * für eine gegebene Auswahl an Keys aus $QUELLEN.
 *
 * @param string[] $keys       Keys aus $QUELLEN, in Anzeigereihenfolge
 * @param string   $accordionId eindeutige HTML-ID für dieses Accordion auf der Seite
 */
function render_quellenliste(array $keys, string $accordionId): string {
    global $QUELLEN;
    $statusLabel = [
        'verifiziert'   => '<span class="badge bg-success-subtle text-success-emphasis">verifiziert</span>',
        'identifiziert' => '<span class="badge bg-warning-subtle text-warning-emphasis">identifiziert</span>',
        'unklar'        => '<span class="badge bg-secondary-subtle text-secondary-emphasis">unklar</span>',
    ];
    $bodyId = $accordionId . '-body';
    $html = '<div class="accordion mt-4" id="' . htmlspecialchars($accordionId, ENT_QUOTES) . '">';
    $html .= '<div class="accordion-item">';
    $html .= '<h2 class="accordion-header">';
    $html .= '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#' . htmlspecialchars($bodyId, ENT_QUOTES) . '">';
    $html .= '<i class="bi bi-journal-text me-2"></i> Quellen zu dieser Seite (' . count($keys) . ')';
    $html .= '</button></h2>';
    $html .= '<div id="' . htmlspecialchars($bodyId, ENT_QUOTES) . '" class="accordion-collapse collapse">';
    $html .= '<div class="accordion-body">';
    $html .= '<p class="text-muted small">Jede Quelle wurde einzeln recherchiert und wo möglich gegen die Originalpublikation geprüft (Autor, Journal, Kennzahlen). '
           . '<span class="badge bg-success-subtle text-success-emphasis">verifiziert</span> = Kennzahlen bestätigt · '
           . '<span class="badge bg-warning-subtle text-warning-emphasis">identifiziert</span> = Publikation eindeutig gefunden, Kennzahlen nicht einzeln nachgeprüft · '
           . '<span class="badge bg-secondary-subtle text-secondary-emphasis">unklar</span> = Themengebiet real, einzelne Quelle nicht zweifelsfrei zuordenbar.</p>';
    $html .= '<ul class="list-unstyled small mb-0">';
    foreach ($keys as $key) {
        if (!isset($QUELLEN[$key])) continue;
        $q = $QUELLEN[$key];
        $html .= '<li class="mb-3 pb-2 border-bottom">';
        $html .= '<div class="d-flex justify-content-between align-items-start gap-2">';
        $html .= '<div>';
        if (!empty($q['doi'])) {
            $html .= '<a href="' . htmlspecialchars($q['doi'], ENT_QUOTES) . '" target="_blank" rel="noopener" class="fw-semibold">' . htmlspecialchars($q['autor']) . '</a>';
        } else {
            $html .= '<span class="fw-semibold">' . htmlspecialchars($q['autor']) . '</span>';
        }
        $html .= '<br><em>' . htmlspecialchars($q['titel']) . '</em>';
        $html .= '<br><span class="text-muted">' . htmlspecialchars($q['journal']) . '</span>';
        $html .= '</div>';
        $html .= '<div class="text-end flex-shrink-0">' . ($statusLabel[$q['status']] ?? '') . '</div>';
        $html .= '</div>';
        if (!empty($q['kennzahl'])) {
            $html .= '<div class="text-muted mt-1">' . htmlspecialchars($q['kennzahl']) . '</div>';
        }
        $html .= '</li>';
    }
    $html .= '</ul></div></div></div></div>';
    return $html;
}
