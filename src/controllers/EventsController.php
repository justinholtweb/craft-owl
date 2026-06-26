<?php

declare(strict_types=1);

namespace justinholtweb\owl\controllers;

use craft\web\Controller;
use justinholtweb\owl\elements\Event;
use justinholtweb\owl\Owl;
use yii\web\Response;

/**
 * Control panel events controller.
 */
class EventsController extends Controller
{
    public function actionIndex(): Response
    {
        $this->requirePermission('owl-manageEvents');

        $events = Event::find()
            ->orderBy(['owl_events.startDate' => SORT_DESC])
            ->limit(50)
            ->all();

        return $this->renderTemplate('owl/_index', [
            'calendars' => Owl::getInstance()->calendars->getAllCalendars(),
            'events' => $events,
        ]);
    }
}
