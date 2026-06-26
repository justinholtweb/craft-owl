<?php

declare(strict_types=1);

namespace justinholtweb\owl\services;

use craft\base\Component;
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
