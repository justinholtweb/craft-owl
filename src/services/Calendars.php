<?php

declare(strict_types=1);

namespace justinholtweb\owl\services;

use Craft;
use craft\base\Component;
use InvalidArgumentException;
use justinholtweb\owl\models\Calendar;
use justinholtweb\owl\records\CalendarRecord;

/**
 * Calendar CRUD.
 *
 * Note: Phase 1 stores calendars directly in the database. Migrating calendar definitions into
 * Project Config (so they version with the rest of the project) is a tracked follow-up.
 */
class Calendars extends Component
{
    /** @var Calendar[]|null */
    private ?array $_calendars = null;

    /**
     * @return Calendar[]
     */
    public function getAllCalendars(): array
    {
        if ($this->_calendars === null) {
            $this->_calendars = [];
            /** @var CalendarRecord[] $records */
            $records = CalendarRecord::find()->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])->all();
            foreach ($records as $record) {
                $this->_calendars[] = $this->createCalendarFromRecord($record);
            }
        }

        return $this->_calendars;
    }

    public function getCalendarById(int $id): ?Calendar
    {
        foreach ($this->getAllCalendars() as $calendar) {
            if ($calendar->id === $id) {
                return $calendar;
            }
        }

        return null;
    }

    /**
     * Clears the in-memory calendar cache (call after creating/updating calendars mid-request).
     */
    public function refresh(): void
    {
        $this->_calendars = null;
    }

    public function getCalendarByHandle(string $handle): ?Calendar
    {
        foreach ($this->getAllCalendars() as $calendar) {
            if ($calendar->handle === $handle) {
                return $calendar;
            }
        }

        return null;
    }

    /**
     * Saves a calendar and its field layout.
     */
    public function save(Calendar $calendar): bool
    {
        if (!$calendar->validate()) {
            return false;
        }

        $record = $calendar->id !== null
            ? CalendarRecord::findOne($calendar->id)
            : new CalendarRecord();

        if ($record === null) {
            throw new InvalidArgumentException("No calendar exists with the id “{$calendar->id}”.");
        }

        $fieldLayout = $calendar->getFieldLayout();
        Craft::$app->getFields()->saveLayout($fieldLayout);
        $calendar->fieldLayoutId = $fieldLayout->id;

        $record->name = $calendar->name;
        $record->handle = $calendar->handle;
        $record->color = $calendar->color;
        $record->fieldLayoutId = $calendar->fieldLayoutId;
        $record->hasTickets = $calendar->hasTickets;
        $record->sortOrder = $calendar->sortOrder;
        $record->save(false);

        $calendar->id = (int)$record->id;
        $calendar->uid = $record->uid;

        $this->refresh();

        return true;
    }

    public function deleteCalendarById(int $id): bool
    {
        $record = CalendarRecord::findOne($id);

        if ($record === null) {
            return true;
        }

        if ($record->fieldLayoutId !== null) {
            Craft::$app->getFields()->deleteLayoutById((int)$record->fieldLayoutId);
        }

        $record->delete();
        $this->refresh();

        return true;
    }

    private function createCalendarFromRecord(CalendarRecord $record): Calendar
    {
        return new Calendar([
            'id' => (int)$record->id,
            'name' => $record->name,
            'handle' => $record->handle,
            'color' => $record->color,
            'fieldLayoutId' => $record->fieldLayoutId !== null ? (int)$record->fieldLayoutId : null,
            'hasTickets' => (bool)$record->hasTickets,
            'sortOrder' => $record->sortOrder !== null ? (int)$record->sortOrder : null,
            'uid' => $record->uid,
        ]);
    }
}
