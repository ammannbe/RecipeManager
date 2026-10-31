<?php

/**
 * Swiss seasonal calendar for vegetables, fruits, salads and herbs, used by `foods:seasons`.
 *
 * Format: food name => [fresh months, storage months], months as numbers 1–12.
 * - Fresh: harvested in Switzerland in the open field or in unheated greenhouses / under fleece.
 *   Produce from heated greenhouses or imports does not count.
 * - Storage: still available from Swiss storage, but not freshly harvested. Storage months do not
 *   count as "in season".
 *
 * Names are written as Swiss shoppers write them and must match foods.name exactly
 * (case-insensitive, trimmed, whitespace-normalized). Foods with other names ("Rüebli, gerieben")
 * are reported by the command as candidates for manual assignment in the backend.
 *
 * Compiled for this app from general knowledge of Swiss growing seasons; months are approximate
 * and vary with region and weather.
 *
 * @return array<string, array{0: list<int>, 1: list<int>}>
 */
return [
    // Vegetables
    'Auberginen' => [[7, 8, 9], []],
    'Blumenkohl' => [[6, 7, 8, 9, 10], []],
    'Bohnen' => [[6, 7, 8, 9], []],
    'Broccoli' => [[6, 7, 8, 9, 10], []],
    'Chicorée' => [[1, 2, 3, 4, 10, 11, 12], []],
    'Chinakohl' => [[7, 8, 9, 10, 11], [12, 1, 2]],
    'Erbsen' => [[6, 7], []],
    'Federkohl' => [[10, 11, 12, 1, 2], []],
    'Fenchel' => [[6, 7, 8, 9, 10], []],
    'Frühlingszwiebeln' => [[4, 5, 6, 7, 8, 9, 10], []],
    'Gurken' => [[5, 6, 7, 8, 9], []],
    'Herbstrüben' => [[9, 10, 11], [12, 1]],
    'Kabis' => [[6, 7, 8, 9, 10, 11], [12, 1, 2, 3, 4]],
    'Kartoffeln' => [[6, 7, 8, 9, 10], [11, 12, 1, 2, 3, 4, 5]],
    'Kefen' => [[5, 6, 7], []],
    'Knoblauch' => [[7, 8], [9, 10, 11, 12, 1, 2, 3]],
    'Knollensellerie' => [[8, 9, 10, 11], [12, 1, 2, 3, 4, 5]],
    'Kohlrabi' => [[5, 6, 7, 8, 9, 10], []],
    'Krautstiel' => [[6, 7, 8, 9, 10], []],
    'Kürbis' => [[8, 9, 10], [11, 12, 1, 2]],
    'Lauch' => [[1, 2, 3, 4, 7, 8, 9, 10, 11, 12], []],
    'Mais' => [[8, 9], []],
    'Mangold' => [[5, 6, 7, 8, 9, 10], []],
    'Meerrettich' => [[10, 11, 12, 1, 2], [3, 4]],
    'Pak Choi' => [[5, 6, 7, 8, 9, 10], []],
    'Pastinaken' => [[10, 11, 12, 1, 2], [3, 4]],
    'Peperoni' => [[7, 8, 9, 10], []],
    'Petersilienwurzeln' => [[10, 11, 12, 1, 2], [3]],
    'Radieschen' => [[4, 5, 6, 7, 8, 9, 10], []],
    'Randen' => [[6, 7, 8, 9, 10], [11, 12, 1, 2, 3, 4]],
    'Rettich' => [[5, 6, 7, 8, 9, 10], []],
    'Romanesco' => [[7, 8, 9, 10], []],
    'Rosenkohl' => [[10, 11, 12, 1, 2], []],
    'Rotkabis' => [[7, 8, 9, 10, 11], [12, 1, 2, 3, 4]],
    'Rüebli' => [[6, 7, 8, 9, 10], [11, 12, 1, 2, 3, 4, 5]],
    'Saubohnen' => [[6, 7], []],
    'Schalotten' => [[7, 8], [9, 10, 11, 12, 1, 2, 3]],
    'Schwarzwurzeln' => [[10, 11, 12, 1, 2, 3], []],
    'Spargel' => [[4, 5, 6], []],
    'Spinat' => [[3, 4, 5, 6, 9, 10, 11], []],
    'Spitzkabis' => [[5, 6, 7, 8, 9, 10], []],
    'Stangensellerie' => [[6, 7, 8, 9, 10], []],
    'Süsskartoffeln' => [[9, 10], [11, 12, 1]],
    'Tomaten' => [[6, 7, 8, 9, 10], []],
    'Topinambur' => [[10, 11, 12, 1, 2, 3], []],
    'Wirz' => [[6, 7, 8, 9, 10, 11, 12], [1, 2, 3]],
    'Zucchetti' => [[6, 7, 8, 9], []],
    'Zwiebeln' => [[7, 8, 9], [10, 11, 12, 1, 2, 3, 4, 5, 6]],
    'Eierschwämmli' => [[7, 8, 9, 10], []],

    // Salads
    'Batavia' => [[5, 6, 7, 8, 9, 10], []],
    'Eisbergsalat' => [[5, 6, 7, 8, 9, 10], []],
    'Endivien' => [[7, 8, 9, 10, 11], []],
    'Kopfsalat' => [[4, 5, 6, 7, 8, 9, 10], []],
    'Lattich' => [[5, 6, 7, 8, 9], []],
    'Löwenzahn' => [[3, 4, 5], []],
    'Nüsslisalat' => [[10, 11, 12, 1, 2, 3], []],
    'Radicchio' => [[8, 9, 10, 11, 12], []],
    'Rucola' => [[4, 5, 6, 7, 8, 9, 10], []],
    'Zuckerhut' => [[10, 11, 12], [1, 2]],

    // Fruits and nuts
    'Äpfel' => [[8, 9, 10, 11], [12, 1, 2, 3, 4, 5, 6, 7]],
    'Aprikosen' => [[7, 8], []],
    'Baumnüsse' => [[9, 10], [11, 12, 1, 2, 3]],
    'Birnen' => [[8, 9, 10, 11], [12, 1, 2, 3, 4]],
    'Brombeeren' => [[7, 8, 9], []],
    'Erdbeeren' => [[5, 6, 7, 8], []],
    'Haselnüsse' => [[9, 10], [11, 12, 1, 2, 3]],
    'Heidelbeeren' => [[7, 8, 9], []],
    'Himbeeren' => [[6, 7, 8, 9, 10], []],
    'Holunderbeeren' => [[8, 9], []],
    'Holunderblüten' => [[5, 6], []],
    'Johannisbeeren' => [[6, 7, 8], []],
    'Kirschen' => [[6, 7, 8], []],
    'Marroni' => [[10, 11], [12]],
    'Melonen' => [[7, 8, 9], []],
    'Mirabellen' => [[8, 9], []],
    'Nektarinen' => [[7, 8, 9], []],
    'Pfirsiche' => [[7, 8, 9], []],
    'Pflaumen' => [[7, 8, 9], []],
    'Quitten' => [[9, 10, 11], [12]],
    'Rhabarber' => [[4, 5, 6], []],
    'Schwarze Johannisbeeren' => [[7, 8], []],
    'Stachelbeeren' => [[6, 7], []],
    'Trauben' => [[9, 10], []],
    'Weichselkirschen' => [[7], []],
    'Zwetschgen' => [[7, 8, 9, 10], []],

    // Herbs
    'Bärlauch' => [[3, 4], []],
    'Basilikum' => [[6, 7, 8, 9], []],
    'Dill' => [[6, 7, 8, 9], []],
    'Kerbel' => [[4, 5, 6, 9, 10], []],
    'Koriander' => [[6, 7, 8, 9], []],
    'Majoran' => [[6, 7, 8, 9], []],
    'Oregano' => [[6, 7, 8, 9], []],
    'Peterli' => [[5, 6, 7, 8, 9, 10], []],
    'Pfefferminze' => [[5, 6, 7, 8, 9], []],
    'Rosmarin' => [[5, 6, 7, 8, 9, 10], []],
    'Salbei' => [[5, 6, 7, 8, 9, 10], []],
    'Schnittlauch' => [[4, 5, 6, 7, 8, 9, 10], []],
    'Thymian' => [[5, 6, 7, 8, 9, 10], []],
    'Zitronenmelisse' => [[5, 6, 7, 8, 9], []],
];
