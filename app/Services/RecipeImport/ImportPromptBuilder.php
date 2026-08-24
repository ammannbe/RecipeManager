<?php

namespace App\Services\RecipeImport;

use App\Models\Category;
use App\Models\Cookbook;
use App\Models\IngredientAttribute;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the instruction text a user hands to an AI together with a recipe photo, PDF or
 * text so the AI returns JSON matching RecipeJsonSchema.
 *
 * Foods are deliberately never listed: the table grows without bound and would dominate
 * the prompt. The importer resolves food names afterwards instead.
 */
class ImportPromptBuilder
{
    /** Keeps the prompt small enough for a typical chat context window. */
    public const MAX_LIST_ENTRIES = 200;

    public function build(?User $user = null): string
    {
        $sections = [
            $this->intro(),
            $this->rules(),
            "## Felder\n\n".RecipeJsonSchema::describe(),
            $this->knownValues($user),
            "## Beispiel\n\n".RecipeJsonSchema::exampleJson(),
        ];

        return implode("\n\n", $sections)."\n";
    }

    private function intro(): string
    {
        return <<<'TXT'
        # Rezept nach JSON konvertieren

        Du erhältst ein Rezept als Foto, PDF, Screenshot oder Text. Wandle es in ein
        einzelnes JSON-Objekt um, das exakt dem unten beschriebenen Schema entspricht.
        TXT;
    }

    private function rules(): string
    {
        return <<<'TXT'
        ## Regeln

        1. Gib ausschliesslich das rohe JSON-Objekt aus. Keine Code-Fences, keine
           Erklärungen, kein einleitender Text.
        2. Gib genau ein Rezept aus. Enthält die Quelle mehrere Rezepte, konvertiere nur
           das erste und weise am Ende nicht darauf hin.
        3. Erfinde nichts. Fehlt eine Angabe in der Quelle, setze null bzw. ein leeres
           Array. Rate niemals Mengen, Zeiten oder Portionen.
        4. Behalte die Sprache der Quelle bei. Übersetze keine Zutaten- oder Feldwerte.
        5. Trenne Menge, Einheit, Lebensmittel und Zubereitungshinweis sauber voneinander.
           Das Feld "food" enthält nur das Lebensmittel im Singular.
        6. Verwende bei den Feldern "category", "unit", "cookbook" und "attributes"
           bevorzugt einen Wert aus den unten aufgeführten bekannten Werten. Passt keiner,
           schreibe die Bezeichnung aus der Quelle - sie wird beim Import nachbearbeitet.
        7. In den Listen der bekannten Werte steht pro Zeile genau ein vollständiger Wert,
           eingeleitet durch "- ". Ein Wert kann Kommas enthalten; übernimm ihn immer als
           Ganzes und zerlege ihn niemals an einem Komma.
        8. Für "food" gibt es bewusst keine Liste. Schreibe die Bezeichnung so, wie sie in
           der Quelle steht, im Singular und ohne Zusätze.
        TXT;
    }

    private function knownValues(?User $user): string
    {
        $blocks = [
            $this->list(__('Complexity'), RecipeJsonSchema::complexities()),
            $this->list(__('Categories'), $this->names(Category::query())),
            $this->list(__('Units'), $this->unitNames()),
            $this->list(__('Ingredient attributes'), $this->names(IngredientAttribute::query())),
            $this->list(__('Tags'), $this->names(Tag::query())),
            $this->list(__('Cookbooks'), $this->cookbookNames($user)),
        ];

        return "## Bekannte Werte\n\n".implode("\n\n", array_filter($blocks));
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return array<int, string>
     */
    private function names($query): array
    {
        return $query->orderBy('name')
            ->limit(self::MAX_LIST_ENTRIES + 1)
            ->pluck('name')
            ->all();
    }

    /**
     * Names only: listing "Gramm (g)" invites the AI to echo that back verbatim, which
     * then matches neither the name nor the shortcut. Abbreviations still resolve on
     * import via the unit's shortcut columns.
     *
     * @return array<int, string>
     */
    private function unitNames(): array
    {
        return $this->names(Unit::query());
    }

    /**
     * @return array<int, string>
     */
    private function cookbookNames(?User $user): array
    {
        $query = Cookbook::query();

        if (! $user?->admin) {
            $query->where('author_id', $user?->author_id);
        }

        return $this->names($query);
    }

    /**
     * One value per line: names may themselves contain commas, so no inline separator
     * would be unambiguous.
     *
     * @param  array<int, string>  $values
     */
    private function list(string $label, array $values): string
    {
        if ($values === []) {
            return '';
        }

        $truncated = count($values) > self::MAX_LIST_ENTRIES;
        $values = array_slice($values, 0, self::MAX_LIST_ENTRIES);

        $lines = array_map(static fn (string $value): string => '- '.$value, $values);

        $block = "### {$label}\n\n".implode("\n", $lines);

        if ($truncated) {
            $block .= "\n\n".__('Only the first :count entries are listed.', [
                'count' => self::MAX_LIST_ENTRIES,
            ]);
        }

        return $block;
    }
}
