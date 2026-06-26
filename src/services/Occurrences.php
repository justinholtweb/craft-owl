<?php

declare(strict_types=1);

namespace justinholtweb\owl\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use justinholtweb\owl\elements\Event;
use justinholtweb\owl\Owl;
use justinholtweb\owl\records\OccurrenceRecord;

/**
 * Materialises and queries event occurrences.
 *
 * Occurrences are generated from an event's recurrence rule up to a rolling horizon and stored as
 * plain rows, so calendar range queries and pagination are fast, indexed SQL.
 */
class Occurrences extends Component
{
    /**
     * Rebuild the occurrence rows for an event (called after save). Synchronous for simple events;
     * heavy recurring events should be pushed to {@see \justinholtweb\owl\jobs\GenerateOccurrencesJob}.
     */
    public function regenerate(Event $event): void
    {
        $db = Craft::$app->getDb();
        $now = Db::prepareDateForDb(new \DateTime());

        $transaction = $db->beginTransaction();
        try {
            OccurrenceRecord::deleteAll(['eventId' => $event->id]);

            $occurrences = Owl::getInstance()->recurrence->expand(
                $event,
                $this->windowStart($event),
                $this->horizonEnd(),
            );

            if ($occurrences !== []) {
                $rows = [];
                foreach ($occurrences as $occurrence) {
                    $rows[] = [
                        $event->id,
                        $occurrence->start->format('Y-m-d H:i:s'),
                        $occurrence->end->format('Y-m-d H:i:s'),
                        $occurrence->allDay,
                        false,
                        false,
                        null,
                        $now,
                        $now,
                        StringHelper::UUID(),
                    ];
                }

                $db->createCommand()->batchInsert(
                    OccurrenceRecord::tableName(),
                    ['eventId', 'startDate', 'endDate', 'allDay', 'isException', 'isOverride', 'overrideData', 'dateCreated', 'dateUpdated', 'uid'],
                    $rows,
                )->execute();
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    private function windowStart(Event $event): DateTimeImmutable
    {
        // Generate from the event's first occurrence (so past occurrences within the rule are stored)
        // up to the horizon.
        return DateTimeImmutable::createFromInterface($event->startDate)->setTimezone(new DateTimeZone('UTC'));
    }

    private function horizonEnd(): DateTimeImmutable
    {
        $months = Owl::getInstance()->getSettings()->occurrenceHorizonMonths;

        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->add(new DateInterval("P{$months}M"));
    }
}
