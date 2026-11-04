# Rezepte importieren

Ein Rezept wird im Backend unter `Rezepte → Rezept importieren` in drei Schritten erfasst. Die KI
(OpenAI, siehe `AGENTS.md`) liest das Rezept aus einem Foto, PDF, Text oder einer Internet-Adresse.

## Ablauf

1. **Rezept einlesen:** Foto oder PDF hochladen und/oder Rezepttext bzw. die Adresse einer
   Rezeptseite einfügen. Optional «Zusätzlich ein Foto mit KI erstellen». Im gleichen Schritt
   werden Kochbuch und Sichtbarkeit gewählt; ohne Kochbuch ist das Rezept standardmässig
   öffentlich, bei einem privaten Kochbuch standardmässig privat. Mit «Weiter» liest die KI das
   Rezept ein (erneut nur, wenn sich die Eingaben geändert haben).
   Unter «Rezept-JSON einfügen» lässt sich stattdessen ein Rezept im JSON-Format einfügen, z. B.
   ein Export. Wer keine KI-Funktionen nutzen darf, sieht nur diese Möglichkeit.
2. **Zuordnen:** Alle Werte, die keiner bestehenden Zeile zugeordnet werden konnten. Pro Wert
   lässt sich ein bestehender Eintrag wählen, ein neuer beantragen bzw. anlegen oder der Wert
   weglassen.
3. **Bestätigen:** Zusammenfassung. Nach dem Import wird das Rezept zur Nachbearbeitung
   geöffnet; ein gewünschtes KI-Foto wird dabei erstellt.

Entwürfe aus der Shoppinglist (`?draft=`) starten direkt bei «Zuordnen», Kochbuch und
Sichtbarkeit stehen dann dort.

## Warum Lebensmittel nicht in der Anweisung an die KI stehen

Die Anweisung (`App\Services\RecipeImport\ImportPromptBuilder`) enthält die vorhandenen
Kategorien, Einheiten, Zutatenattribute, Schlagwörter und Kochbücher, aber keine Lebensmittel:
deren Tabelle wächst mit jedem Rezept. Lebensmittelnamen schreibt die KI frei; beim Import werden
sie über einen Namensabgleich mit Ähnlichkeitsvorschlägen zugeordnet. Alle Listen sind auf 200
Einträge begrenzt. Das Schema liegt in `App\Services\RecipeImport\RecipeJsonSchema`.

```bash
ddev artisan tinker --execute="echo app(App\Services\RecipeImport\ImportPromptBuilder::class)->build();"
```

## Export

Auf der Bearbeitungsseite eines Rezepts erzeugt **Als JSON exportieren** eine Datei im gleichen
Format. Sie kann unter «Rezept-JSON einfügen» wieder importiert werden, etwa um ein Rezept in ein
anderes Kochbuch zu kopieren. Bilder werden beim Export standardmässig weggelassen.

## Grenzen

- Pro Import genau ein Rezept.
- Bilder im JSON entweder als Base64-Data-URI oder als öffentliche, direkt herunterladbare URL.
  Erlaubt sind JPEG, PNG, WebP und GIF bis 5 MB, maximal 10 Bilder. Optional pro Bild eine Quelle
  (`source`) und ein KI-generiert-Kennzeichen (`is_ai_generated`).
- KI-Fotos erhalten die Quelle `OPENAI_IMAGE_SOURCE` (Standard «ChatGPT (OpenAI)»).
- Ein Rezept mit gleichem Namen im selben Kochbuch wird abgelehnt.
