<?php

namespace App\Mail;

use App\Filament\Resources\Foods\FoodResource;
use App\Filament\Resources\IngredientAttributes\IngredientAttributeResource;
use App\Filament\Resources\Tags\TagResource;
use App\Filament\Resources\Units\UnitResource;
use App\Services\RecipeImport\LookupType;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells an admin that a user requested foods, units, attributes or tags while importing.
 */
class LookupRequestedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, array{type: string, value: string}>  $requests
     */
    public function __construct(
        public string $requestedBy,
        public string $recipe,
        public array $requests,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':name requested new entries', ['name' => $this->requestedBy]),
        );
    }

    public function content(): Content
    {
        $items = array_map(function (array $request): array {
            $type = LookupType::from($request['type']);

            return [
                'label' => $type->label(),
                'value' => $request['value'],
                'url' => match ($type) {
                    LookupType::Food => FoodResource::getUrl(),
                    LookupType::Unit => UnitResource::getUrl(),
                    LookupType::Attribute => IngredientAttributeResource::getUrl(),
                    default => TagResource::getUrl(),
                },
            ];
        }, $this->requests);

        return new Content(
            markdown: 'mail.lookup-requested',
            with: [
                'requestedBy' => $this->requestedBy,
                'recipe' => $this->recipe,
                'items' => $items,
            ],
        );
    }
}
