# Rezepte per JSON importieren

Ein Rezept kann als JSON importiert werden. Das JSON lässt sich von einer beliebigen KI aus
einem Foto, PDF, Screenshot oder Text erzeugen.

## Ablauf

1. Im Backend `Rezepte → Rezept importieren` öffnen.
2. Im ersten Schritt die Anweisung mit **Anweisung kopieren** in die Zwischenablage
   übernehmen. Sie enthält zusätzlich die im System bereits vorhandenen Kategorien,
   Einheiten, Zutatenattribute, Schlagwörter und Kochbücher.
3. Die Anweisung in einen KI-Chat einfügen und das Rezept als Foto, PDF oder Text anhängen.
4. Die JSON-Antwort der KI in den zweiten Schritt einfügen.
5. Im dritten Schritt werden alle Werte aufgelistet, die keiner bestehenden Zeile zugeordnet
   werden konnten. Pro Wert lässt sich ein bestehender Eintrag auswählen; Administratoren
   können den Wert stattdessen neu anlegen. Kochbücher und Kategorien darf jede Person neu
   anlegen, Lebensmittel, Einheiten, Schlagwörter und Zutatenattribute nur Administratoren.
6. Der letzte Schritt zeigt eine Zusammenfassung. Nach dem Import wird das Rezept zur
   Nachbearbeitung geöffnet.

## Warum Lebensmittel nicht in der Anweisung stehen

Die Tabelle der Lebensmittel wächst mit jedem Rezept und würde die Anweisung dominieren.
Kategorien, Einheiten, Attribute, Schlagwörter und Kochbücher sind dagegen kleine,
weitgehend geschlossene Mengen und werden mitgeschickt. Lebensmittelnamen schreibt die KI
frei; beim Import werden sie über einen Namensabgleich mit Ähnlichkeitsvorschlägen
zugeordnet.

Alle Listen sind auf 200 Einträge begrenzt. Wird eine Liste gekürzt, weist die Anweisung
darauf hin.

## Der Prompt

Die Anweisung wird zur Laufzeit aus `App\Services\RecipeImport\ImportPromptBuilder` erzeugt,
damit sie nie vom tatsächlich geprüften Schema abweicht. Das Schema selbst liegt in
`App\Services\RecipeImport\RecipeJsonSchema`.

Zum Ansehen der aktuellen Anweisung ausserhalb des Backends:

```bash
ddev artisan tinker --execute="echo app(App\Services\RecipeImport\ImportPromptBuilder::class)->build();"
```

## Export

Auf der Bearbeitungsseite eines Rezepts erzeugt **Als JSON exportieren** eine Datei im
gleichen Format. Sie kann unverändert wieder importiert werden, etwa um ein Rezept in ein
anderes Kochbuch zu kopieren. Bilder werden beim Export standardmässig weggelassen.

## Grenzen

- Pro Datei genau ein Rezept.
- Bilder nur als Base64-Data-URI, keine URLs. Erlaubt sind JPEG, PNG, WebP und GIF bis 5 MB,
  maximal 10 Bilder.
- Bewertungen werden nicht importiert.
- Ein Rezept mit gleichem Namen im selben Kochbuch wird abgelehnt.
