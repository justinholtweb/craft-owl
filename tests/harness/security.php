<?php
/**
 * Owl's anonymous JSON feed and its calendar colours — checked in the plugin-testing harness.
 *
 *     docker exec -w /var/www/html ddev-plugin-testing-web php /var/www/craft-owl/tests/harness/security.php
 *
 * The harness runs Owl mounted inside Showtime — the code synced from this repo. Until 5.2.1,
 * `events.json` answered for any range, so one anonymous request for a decade returned every
 * occurrence on the site, and a calendar's colour could be any string, drawn as an inline style.
 * (The ICS feed filter is covered by Showtime's `showtime/test/run`, which owns the gate.)
 *
 * Self-cleaning.
 */

$root = getcwd();
require $root . '/bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

use GuzzleHttp\Client;
use justinholtweb\owl\controllers\FeedController;
use justinholtweb\owl\elements\Event as OwlEvent;
use justinholtweb\owl\models\Calendar;
use justinholtweb\owl\Owl;

$passed = 0;
$failed = 0;

function check(string $label, callable $test): void
{
    global $passed, $failed;

    try {
        $result = $test();
    } catch (Throwable $e) {
        $result = get_class($e) . ': ' . $e->getMessage();
    }

    if ($result === true) {
        $passed++;
        echo "  ✓ $label\n";
    } else {
        $failed++;
        echo "  ✗ $label\n    " . (is_string($result) ? $result : var_export($result, true)) . "\n";
    }
}

Craft::$app->getPlugins()->loadPlugins();

$owl = Owl::getInstance();

if ($owl === null) {
    echo "Owl isn't loaded (standalone or mounted in Showtime).\n";
    exit(1);
}

$run = substr(bin2hex(random_bytes(3)), 0, 6);
$cleanup = ['calendar' => null, 'events' => []];

register_shutdown_function(function() use (&$cleanup, $owl) {
    foreach ($cleanup['events'] as $event) {
        Craft::$app->getElements()->deleteElement($event, true);
    }
    if ($cleanup['calendar']) {
        $owl->calendars->deleteCalendarById($cleanup['calendar']->id);
    }

    // Calendars are project config, buffered in a console script: out to the database and the
    // YAML, or the calendar stays and the next web request sees pending changes.
    Craft::$app->getProjectConfig()->saveModifiedConfigData();
    Craft::$app->getProjectConfig()->writeYamlFiles(true);
});

$calendar = new Calendar(['name' => "Owl security $run", 'handle' => "owlSecurity$run", 'color' => '#336699']);
$owl->calendars->save($calendar) or throw new RuntimeException(json_encode($calendar->getErrors()));
$cleanup['calendar'] = $calendar;
Craft::$app->getProjectConfig()->saveModifiedConfigData();
Craft::$app->getProjectConfig()->writeYamlFiles(true);

// One event soon, one 500 days out: past the 400-day window, inside the 24-month occurrence horizon.
foreach (['Soon' => '+10 days', 'Far' => '+500 days'] as $label => $when) {
    $start = new DateTime("$when 10:00", new DateTimeZone('UTC'));
    $event = new OwlEvent([
        'calendarId' => $calendar->id,
        'title' => "Owl security $label $run",
        'startDate' => $start,
        'endDate' => (clone $start)->modify('+1 hour'),
        'timezone' => 'UTC',
    ]);
    Craft::$app->getElements()->saveElement($event) or throw new RuntimeException(json_encode($event->getErrors()));
    $cleanup['events'][] = $event;
}

$http = new Client(['base_uri' => 'http://localhost/', 'http_errors' => false]);
$feed = static fn(array $query) => json_decode((string)$http->get('index.php?p=actions/owl/feed/events&' . http_build_query($query + ['calendar' => "owlSecurity$run"]))->getBody(), true) ?? [];
$titles = static fn(array $items) => array_column($items, 'title');

echo "\nevents.json\n";

check('a decade-wide request is cut to one window', function() use ($feed, $titles, $run) {
    $items = $titles($feed(['start' => (new DateTime('-1 day'))->format('Y-m-d'), 'end' => (new DateTime('+10 years'))->format('Y-m-d')]));

    return in_array("Owl security Soon $run", $items, true) && !in_array("Owl security Far $run", $items, true) ?: json_encode($items);
});

check('a request for the far event’s own window still finds it', function() use ($feed, $titles, $run) {
    $items = $titles($feed(['start' => (new DateTime('+495 days'))->format('Y-m-d'), 'end' => (new DateTime('+505 days'))->format('Y-m-d')]));

    return in_array("Owl security Far $run", $items, true) ?: json_encode($items);
});

check('a backwards range answers nothing', fn() => $feed(['start' => '2030-01-02', 'end' => '2030-01-01']) === [] ?: 'answered');

check('the caps are what the docs say', fn() => FeedController::MAX_RANGE_DAYS === 400 && FeedController::MAX_ROWS === 2000 ?: 'changed');

echo "\nCalendar colours\n";

check('a colour has to be a colour', function() use ($run) {
    $calendar = new Calendar(['name' => "Owl bad $run", 'handle' => "owlBad$run", 'color' => 'red; background-image: url(https://x.example/)']);

    return !$calendar->validate() && $calendar->hasErrors('color') ?: 'accepted';
});

check('…and a hex colour is fine', function() use ($run) {
    $calendar = new Calendar(['name' => "Owl good $run", 'handle' => "owlGood$run", 'color' => '#7C5CFF']);

    return $calendar->validate() ?: json_encode($calendar->getErrors());
});

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
