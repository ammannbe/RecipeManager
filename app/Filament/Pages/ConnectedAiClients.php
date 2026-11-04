<?php

namespace App\Filament\Pages;

use App\Mcp\McpPermission;
use App\Models\McpGrant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * AI clients (MCP) the user connected: their permissions (changeable), last use, revoking,
 * and how to connect Claude Code, Codex, OpenClaw and others.
 */
class ConnectedAiClients extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 90;

    public static function getNavigationLabel(): string
    {
        return __('Connected AI clients');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Connected AI clients');
    }

    public function content(Schema $schema): Schema
    {
        $url = url('/mcp');
        $name = str(config('app.name'))->slug()->toString();

        return $schema->components([
            Section::make(__('Connect an AI client'))
                ->description(__('AI clients like Claude Code, Codex or OpenClaw connect over MCP. Add the address below, then sign in and choose the permissions when the client asks. No plugin is needed.'))
                ->collapsible()
                ->schema([
                    Text::make(new HtmlString(implode('', [
                        '<p><strong>'.e(__('Address (MCP server)')).':</strong> <code>'.e($url).'</code></p>',
                        '<p class="mt-3"><strong>Claude Code:</strong></p><pre class="overflow-x-auto rounded-lg bg-zinc-100 p-3 text-xs dark:bg-zinc-800"><code>claude mcp add --transport http '.e($name).' '.e($url).'</code></pre><p class="text-xs">'.e(__('Then run /mcp in Claude Code and choose "Authenticate".')).'</p>',
                        '<p class="mt-3"><strong>Codex:</strong></p><pre class="overflow-x-auto rounded-lg bg-zinc-100 p-3 text-xs dark:bg-zinc-800"><code>codex mcp add '.e($name).' --url '.e($url)."\ncodex mcp login ".e($name).'</code></pre>',
                        '<p class="mt-3"><strong>OpenClaw:</strong> '.e(__('ask your agent: "Connect the MCP server :url as :name" or add it in the settings under MCP servers; the sign-in opens in the browser.', ['url' => $url, 'name' => $name])).'</p>',
                        '<p class="mt-3"><strong>'.e(__('Other clients (Claude Desktop, ChatGPT, Cursor …)')).':</strong> '.e(__('add a remote MCP server with this address and OAuth.')).'</p>',
                    ]))),
                ]),
            Section::make(__('Connected clients'))
                ->schema([EmbeddedTable::make()]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => McpGrant::query()->where('user_id', user()?->getKey())->with('client'))
            ->columns([
                TextColumn::make('client.name')->label(__('Client')),
                TextColumn::make('permissions')
                    ->label(__('Permissions'))
                    ->formatStateUsing(fn (string $state): string => McpPermission::tryFrom($state)?->label() ?? $state)
                    ->badge(),
                TextColumn::make('last_used_at')->label(__('Last used'))->since()->placeholder(__('never')),
                TextColumn::make('created_at')->label(__('Connected'))->date(),
            ])
            ->recordActions([
                Action::make('permissions')
                    ->label(__('Permissions'))
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->fillForm(fn (McpGrant $record): array => ['permissions' => $record->permissions])
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label(__('Permissions'))
                            ->options(McpPermission::options())
                            ->required(),
                    ])
                    ->action(function (McpGrant $record, array $data): void {
                        $record->update(['permissions' => array_values($data['permissions'])]);

                        Notification::make()->success()->title(__('Saved'))->send();
                    }),
                Action::make('revoke')
                    ->label(__('Revoke'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('The client loses access right away. To use it again, connect it again.'))
                    ->action(function (McpGrant $record): void {
                        self::revoke($record);

                        Notification::make()->success()->title(__('Access revoked'))->send();
                    }),
            ])
            ->emptyStateHeading(__('No AI client connected yet'))
            ->paginated(false);
    }

    /**
     * Revokes the client's tokens of this user and forgets the permissions.
     */
    public static function revoke(McpGrant $grant): void
    {
        DB::transaction(function () use ($grant): void {
            $tokens = DB::table('oauth_access_tokens')
                ->where('user_id', $grant->user_id)
                ->where('client_id', $grant->oauth_client_id);

            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', (clone $tokens)->pluck('id'))->update(['revoked' => true]);
            $tokens->update(['revoked' => true]);

            $grant->delete();
        });
    }
}
