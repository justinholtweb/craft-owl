<?php

declare(strict_types=1);

namespace justinholtweb\owl\models;

use Craft;
use craft\base\Model;
use craft\behaviors\FieldLayoutBehavior;
use craft\models\FieldLayout;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;
use justinholtweb\owl\elements\Event;
use justinholtweb\owl\records\CalendarRecord;

/**
 * A calendar groups events, owns a field layout, a colour, and (Pro) a ticketing toggle.
 *
 * @mixin FieldLayoutBehavior
 */
class Calendar extends Model
{
    public ?int $id = null;
    public ?int $fieldLayoutId = null;
    public string $name = '';
    public string $handle = '';
    public ?string $color = null;
    public bool $hasTickets = false;
    public ?int $sortOrder = null;
    public ?string $uid = null;

    public function behaviors(): array
    {
        return [
            'fieldLayout' => [
                'class' => FieldLayoutBehavior::class,
                'elementType' => Event::class,
            ],
        ];
    }

    public function defineRules(): array
    {
        return [
            [['name', 'handle'], 'required'],
            [['name', 'handle', 'color'], 'string', 'max' => 255],
            [['handle'], HandleValidator::class],
            [['handle'], UniqueValidator::class, 'targetClass' => CalendarRecord::class],
        ];
    }

    public function getFieldLayout(): ?FieldLayout
    {
        /** @var FieldLayoutBehavior $behavior */
        $behavior = $this->getBehavior('fieldLayout');

        return $behavior->getFieldLayout();
    }

    public function getCpEditUrl(): string
    {
        return Craft::$app->getRequest()->getHostInfo() !== ''
            ? "owl/calendars/{$this->handle}"
            : '';
    }
}
