<?php

declare(strict_types=1);

namespace justinholtweb\owl\services;

use Craft;
use craft\base\Component;
use craft\helpers\StringHelper;
use justinholtweb\owl\elements\Event;
use justinholtweb\owl\elements\Ticket;

/**
 * Manages event tickets (Commerce purchasables). Pro edition + Craft Commerce required; this service
 * is only reachable when Commerce is installed (the element/purchasable types are registered then).
 */
class Tickets extends Component
{
    /**
     * @return Ticket[]
     */
    public function getTicketsForEvent(int $eventId): array
    {
        /** @var Ticket[] $tickets */
        $tickets = Ticket::find()->eventId($eventId)->status(null)->all();

        return $tickets;
    }

    public function getTicketById(int $id): ?Ticket
    {
        $ticket = Ticket::find()->id($id)->status(null)->one();

        return $ticket instanceof Ticket ? $ticket : null;
    }

    /**
     * Creates and saves a ticket for an event.
     */
    public function createTicket(
        Event $event,
        string $name,
        float $price,
        ?int $capacity = null,
        ?string $sku = null,
    ): Ticket {
        $ticket = new Ticket();
        $ticket->eventId = (int)$event->id;
        $ticket->siteId = $event->siteId ?? Craft::$app->getSites()->getPrimarySite()->id;
        $ticket->ticketName = $name;
        $ticket->capacity = $capacity;
        $ticket->sku = $sku ?? $this->generateSku($event, $name);
        $ticket->basePrice = $price;
        $ticket->availableForPurchase = true;

        Craft::$app->getElements()->saveElement($ticket);

        return $ticket;
    }

    private function generateSku(Event $event, string $name): string
    {
        $slug = strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $name));

        return sprintf('OWL-%d-%s-%s', $event->id, $slug, strtoupper(substr(StringHelper::UUID(), 0, 4)));
    }
}
