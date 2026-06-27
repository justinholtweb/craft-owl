<?php

declare(strict_types=1);

namespace justinholtweb\owl\controllers;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use DateTime;
use DateTimeZone;
use justinholtweb\owl\Owl;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Front-end feeds: a FullCalendar-compatible JSON endpoint and ICS exports.
 */
class FeedController extends Controller
{
    protected array|bool|int $allowAnonymous = true;

    /**
     * JSON event feed shaped for FullCalendar. Reads the `start`/`end` range it requests and
     * returns only occurrences overlapping that window.
     *
     *   GET owl/events.json?start=…&end=…&calendar=concerts
     */
    public function actionEvents(): Response
    {
        $request = Craft::$app->getRequest();

        $rangeStart = DateTimeHelper::toDateTime($request->getParam('start')) ?: new DateTime('-1 year');
        $rangeEnd = DateTimeHelper::toDateTime($request->getParam('end')) ?: new DateTime('+1 year');
        $calendarIds = $this->resolveCalendarIds($request->getParam('calendar'));

        $rows = Owl::getInstance()->occurrences->getOccurrencesInRange($rangeStart, $rangeEnd, null, $calendarIds);
        $utc = new DateTimeZone('UTC');

        $events = [];
        foreach ($rows as $row) {
            $events[] = [
                'id' => (int)$row['eventId'],
                'title' => (string)$row['title'],
                'start' => (new DateTime((string)$row['startDate'], $utc))->format('c'),
                'end' => (new DateTime((string)$row['endDate'], $utc))->format('c'),
                'allDay' => (bool)$row['allDay'],
                'url' => !empty($row['uri']) ? UrlHelper::siteUrl((string)$row['uri']) : null,
                'color' => $row['color'] ?: null,
            ];
        }

        return $this->asJson($events);
    }

    /**
     * Subscribable ICS feed for a calendar: owl/calendar/<handle>.ics
     */
    public function actionCalendar(string $handle): Response
    {
        $calendar = Owl::getInstance()->calendars->getCalendarByHandle($handle);

        if ($calendar === null) {
            throw new NotFoundHttpException('Calendar not found.');
        }

        $ics = Owl::getInstance()->ics->calendarFeed($calendar);

        return $this->icsResponse($ics, $handle);
    }

    /**
     * ICS download for a single event: owl/event/<id>.ics
     */
    public function actionEvent(int $eventId): Response
    {
        $event = Owl::getInstance()->events->getEventById($eventId);

        if ($event === null || $event->getStatus() !== \craft\base\Element::STATUS_ENABLED) {
            throw new NotFoundHttpException('Event not found.');
        }

        $ics = Owl::getInstance()->ics->eventFeed($event);

        return $this->icsResponse($ics, 'event-' . $eventId);
    }

    /**
     * @return int[]|null
     */
    private function resolveCalendarIds(mixed $param): ?array
    {
        if ($param === null || $param === '') {
            return null;
        }

        $handles = is_array($param) ? $param : explode(',', (string)$param);
        $ids = [];
        foreach ($handles as $handle) {
            $calendar = Owl::getInstance()->calendars->getCalendarByHandle(trim((string)$handle));
            if ($calendar !== null) {
                $ids[] = $calendar->id;
            }
        }

        return $ids !== [] ? $ids : [0];
    }

    private function icsResponse(string $ics, string $filename): Response
    {
        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', "inline; filename=\"{$filename}.ics\"");
        $response->content = $ics;

        return $response;
    }
}
