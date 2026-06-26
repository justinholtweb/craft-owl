<?php

declare(strict_types=1);

namespace justinholtweb\owl\web\twig;

use Craft;
use justinholtweb\owl\elements\db\EventQuery;
use justinholtweb\owl\elements\Event;
use justinholtweb\owl\models\Calendar;
use justinholtweb\owl\Owl;

/**
 * The object returned by `craft.owl` in Twig.
 *
 * Usage:
 *   {% set upcoming = craft.owl.events.startsAfter(now).orderBy('startDate ASC').limit(10).all() %}
 *   {% set calendars = craft.owl.calendars() %}
 */
class OwlVariable
{
    /**
     * Returns an Event element query, optionally configured from a criteria hash.
     */
    public function events(array $criteria = []): EventQuery
    {
        /** @var EventQuery $query */
        $query = Event::find();

        if ($criteria !== []) {
            Craft::configure($query, $criteria);
        }

        return $query;
    }

    /**
     * @return Calendar[]
     */
    public function calendars(): array
    {
        return Owl::getInstance()->calendars->getAllCalendars();
    }
}
