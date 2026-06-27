<?php

declare(strict_types=1);

namespace justinholtweb\owl\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use justinholtweb\owl\elements\Event;
use justinholtweb\owl\jobs\GenerateOccurrencesJob;
use justinholtweb\owl\Owl;
use yii\console\ExitCode;

/**
 * Maintenance tasks. Run `owl/maintenance/regenerate` from a daily cron to roll the occurrence
 * generation horizon forward (so open-ended recurrence rules keep materialising future dates):
 *
 *   # crontab — every night at 03:00
 *   0 3 * * * cd /path/to/project && php craft owl/maintenance/regenerate >> /dev/null 2>&1
 */
class MaintenanceController extends Controller
{
    /**
     * Push each event's regeneration onto the queue instead of running it inline (recommended for
     * sites with many recurring events).
     */
    public bool $queue = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['queue']);
    }

    /**
     * Regenerates the materialised occurrences for every recurring event, rolling the horizon
     * forward. Non-recurring events are skipped — their single occurrence never changes with time.
     */
    public function actionRegenerate(): int
    {
        $events = Event::find()->repeating(true)->status(null)->all();
        $total = count($events);

        if ($total === 0) {
            $this->stdout("No recurring events to regenerate.\n");
            return ExitCode::OK;
        }

        $queueService = Craft::$app->getQueue();
        $count = 0;

        foreach ($events as $event) {
            /** @var Event $event */
            if ($this->queue) {
                $queueService->push(new GenerateOccurrencesJob([
                    'eventId' => (int)$event->id,
                    'siteId' => $event->siteId,
                ]));
            } else {
                Owl::getInstance()->occurrences->regenerate($event);
            }

            $count++;
        }

        $verb = $this->queue ? 'Queued regeneration for' : 'Regenerated occurrences for';
        $this->stdout("{$verb} {$count} recurring event(s).\n", Console::FG_GREEN);

        return ExitCode::OK;
    }
}
