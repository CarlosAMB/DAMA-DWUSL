<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Genera y lee archivos iCalendar (.ics, RFC 5545).
 * Es el formato estandar que entienden Google Calendar, Outlook y Apple Calendar.
 */
class IcsService
{
    public function generate(Collection $events, Collection $tasks, string $calendarName = 'EasyLife'): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//EasyLife//Organizador Personal//ES',
            'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:'.$this->escape($calendarName),
        ];

        foreach ($events as $event) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$event->id.'@easylife';
            $lines[] = 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z');
            if ($event->all_day) {
                $lines[] = 'DTSTART;VALUE=DATE:'.$event->start_at->format('Ymd');
                $lines[] = 'DTEND;VALUE=DATE:'.($event->end_at ?? $event->start_at)->copy()->addDay()->format('Ymd');
            } else {
                $lines[] = 'DTSTART:'.$event->start_at->utc()->format('Ymd\THis\Z');
                $lines[] = 'DTEND:'.($event->end_at ?? $event->start_at->copy()->addHour())->utc()->format('Ymd\THis\Z');
            }
            $lines[] = 'SUMMARY:'.$this->escape($event->title);
            if ($event->description) {
                $lines[] = 'DESCRIPTION:'.$this->escape($event->description);
            }
            if ($event->location) {
                $lines[] = 'LOCATION:'.$this->escape($event->location);
            }
            if ($event->type) {
                $lines[] = 'CATEGORIES:'.$this->escape($event->type->name);
            }
            $lines[] = 'END:VEVENT';
        }

        foreach ($tasks as $task) {
            $lines[] = 'BEGIN:VTODO';
            $lines[] = 'UID:'.$task->id.'@easylife';
            $lines[] = 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:'.$this->escape($task->title);
            if ($task->description) {
                $lines[] = 'DESCRIPTION:'.$this->escape($task->description);
            }
            if ($task->due_date) {
                $lines[] = 'DUE:'.$task->due_date->utc()->format('Ymd\THis\Z');
            }
            $lines[] = 'PRIORITY:'.['urgente' => 1, 'alta' => 3, 'media' => 5, 'baja' => 9][$task->priority];
            $lines[] = 'STATUS:'.($task->status === 'completada' ? 'COMPLETED' : ($task->status === 'en_progreso' ? 'IN-PROCESS' : 'NEEDS-ACTION'));
            $lines[] = 'END:VTODO';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([$this, 'fold'], $lines))."\r\n";
    }

    /**
     * Lee un .ics y devuelve ['events' => [...], 'tasks' => [...]] con arreglos listos para guardar.
     */
    public function parse(string $content, string $timezone = 'UTC'): array
    {
        // "Desdoblar" lineas largas (las que continuan empiezan con espacio o tab)
        $content = preg_replace("/\r\n[ \t]|\n[ \t]/", '', $content);
        $lines = preg_split("/\r\n|\n|\r/", $content);

        $result = ['events' => [], 'tasks' => []];
        $current = null;
        $kind = null;

        foreach ($lines as $line) {
            if ($line === 'BEGIN:VEVENT' || $line === 'BEGIN:VTODO') {
                $kind = $line === 'BEGIN:VEVENT' ? 'events' : 'tasks';
                $current = [];
                continue;
            }
            if ($line === 'END:VEVENT' || $line === 'END:VTODO') {
                if ($current !== null) {
                    $result[$kind][] = $current;
                }
                $current = null;
                continue;
            }
            if ($current === null || ! str_contains($line, ':')) {
                continue;
            }

            [$rawKey, $value] = explode(':', $line, 2);
            $parts = explode(';', $rawKey);
            $key = strtoupper($parts[0]);
            $params = strtoupper(implode(';', array_slice($parts, 1)));

            switch ($key) {
                case 'SUMMARY':
                    $current['title'] = mb_substr($this->unescape($value), 0, 150);
                    break;
                case 'DESCRIPTION':
                    $current['description'] = mb_substr($this->unescape($value), 0, 5000);
                    break;
                case 'LOCATION':
                    $current['location'] = mb_substr($this->unescape($value), 0, 150);
                    break;
                case 'DTSTART':
                    $current['start_at'] = $this->parseDate($value, $params, $timezone);
                    $current['all_day'] = str_contains($params, 'VALUE=DATE') || strlen($value) === 8;
                    break;
                case 'DTEND':
                    $current['end_at'] = $this->parseDate($value, $params, $timezone);
                    break;
                case 'DUE':
                    $current['due_date'] = $this->parseDate($value, $params, $timezone);
                    break;
                case 'PRIORITY':
                    $p = (int) $value;
                    $current['priority'] = $p === 0 ? 'media' : ($p <= 2 ? 'urgente' : ($p <= 4 ? 'alta' : ($p <= 6 ? 'media' : 'baja')));
                    break;
                case 'STATUS':
                    $current['status'] = match (strtoupper($value)) {
                        'COMPLETED' => 'completada',
                        'IN-PROCESS' => 'en_progreso',
                        default => 'pendiente',
                    };
                    break;
            }
        }

        // Los eventos de dia completo terminan (exclusivo) al dia siguiente en ICS
        foreach ($result['events'] as &$event) {
            if (! empty($event['all_day']) && ! empty($event['end_at'])) {
                $event['end_at'] = $event['end_at']->copy()->subDay();
            }
        }

        return $result;
    }

    private function parseDate(string $value, string $params, string $timezone): ?Carbon
    {
        try {
            if (strlen($value) === 8) { // 20261115
                return Carbon::createFromFormat('Ymd', $value, $timezone)->startOfDay()->utc();
            }
            if (str_ends_with($value, 'Z')) { // 20261115T140000Z (UTC)
                return Carbon::createFromFormat('Ymd\THis\Z', $value, 'UTC');
            }
            $tz = preg_match('/TZID=([^;]+)/', $params, $m) ? $m[1] : $timezone;
            try {
                return Carbon::createFromFormat('Ymd\THis', $value, $tz)->utc();
            } catch (\Throwable) {
                return Carbon::createFromFormat('Ymd\THis', $value, $timezone)->utc();
            }
        } catch (\Throwable) {
            return null;
        }
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $text);
    }

    private function unescape(string $text): string
    {
        return str_replace(['\\n', '\\N', '\;', '\,', '\\\\'], ["\n", "\n", ';', ',', '\\'], $text);
    }

    /** Las lineas ICS no deben superar 75 octetos. */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        while (strlen($line) > 75) {
            $cut = 75;
            // no cortar en medio de un caracter UTF-8
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $out .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
        }

        return $out.$line;
    }
}
