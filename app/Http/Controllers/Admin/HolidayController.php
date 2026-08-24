<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Holiday;
use Carbon\Carbon;

class HolidayController extends Controller
{
    public function index(Request $request): Response
    {
        $year = (int) $request->query('year', Carbon::now('Asia/Jakarta')->year);

        $holidays = Holiday::orderBy('date', 'asc')
            ->get()
            ->map(function ($h) {
                return [
                    'id' => $h->id,
                    'name' => $h->name,
                    'date' => $h->date ? $h->date->format('Y-m-d') : null,
                    'formatted_date' => $h->date ? $h->date->format('D, d M Y') : '-',
                    'month' => $h->date ? $h->date->format('F') : '-',
                    'is_recurring' => (bool) $h->is_recurring,
                ];
            });

        $upcomingCount = Holiday::where('date', '>=', Carbon::today('Asia/Jakarta'))
            ->count();

        return Inertia::render('Admin/Holidays/Index', [
            'holidays' => $holidays,
            'year' => $year,
            'stats' => [
                'total_holidays' => $holidays->count(),
                'upcoming_holidays' => $upcomingCount,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'is_recurring' => 'boolean',
        ]);

        Holiday::create([
            'name' => $validated['name'],
            'date' => $validated['date'],
            'is_recurring' => $validated['is_recurring'] ?? false,
            'created_at' => Carbon::now(),
        ]);

        return redirect()->route('admin.holidays')->with('success', "Holiday '{$validated['name']}' has been added.");
    }

    public function destroy(Holiday $holiday)
    {
        $name = $holiday->name;
        $holiday->delete();

        return redirect()->route('admin.holidays')->with('success', "Holiday '{$name}' deleted.");
    }

    public function importNationalHolidays(Request $request)
    {
        $year = (int) $request->input('year', Carbon::now('Asia/Jakarta')->year);

        $presetHolidays = [
            ['name' => 'Tahun Baru Masehi', 'date' => "{$year}-01-01", 'is_recurring' => true],
            ['name' => 'Tahun Baru Imlek', 'date' => "{$year}-02-10", 'is_recurring' => false],
            ['name' => 'Hari Suci Nyepi', 'date' => "{$year}-03-11", 'is_recurring' => false],
            ['name' => 'Wafat Isa Al Masih', 'date' => "{$year}-03-29", 'is_recurring' => false],
            ['name' => 'Hari Raya Idul Fitri (Hari 1)', 'date' => "{$year}-04-10", 'is_recurring' => false],
            ['name' => 'Hari Raya Idul Fitri (Hari 2)', 'date' => "{$year}-04-11", 'is_recurring' => false],
            ['name' => 'Hari Buruh Internasional', 'date' => "{$year}-05-01", 'is_recurring' => true],
            ['name' => 'Kenaikan Isa Al Masih', 'date' => "{$year}-05-09", 'is_recurring' => false],
            ['name' => 'Hari Raya Waisak', 'date' => "{$year}-05-23", 'is_recurring' => false],
            ['name' => 'Hari Lahir Pancasila', 'date' => "{$year}-06-01", 'is_recurring' => true],
            ['name' => 'Hari Raya Idul Adha', 'date' => "{$year}-06-17", 'is_recurring' => false],
            ['name' => 'Tahun Baru Islam (1 Muharram)', 'date' => "{$year}-07-07", 'is_recurring' => false],
            ['name' => 'Hari Kemerdekaan RI', 'date' => "{$year}-08-17", 'is_recurring' => true],
            ['name' => 'Maulid Nabi Muhammad SAW', 'date' => "{$year}-09-16", 'is_recurring' => false],
            ['name' => 'Hari Raya Natal', 'date' => "{$year}-12-25", 'is_recurring' => true],
        ];

        $count = 0;
        foreach ($presetHolidays as $h) {
            $exists = Holiday::where('date', $h['date'])->where('name', $h['name'])->exists();
            if (!$exists) {
                Holiday::create([
                    'name' => $h['name'],
                    'date' => $h['date'],
                    'is_recurring' => $h['is_recurring'],
                    'created_at' => Carbon::now(),
                ]);
                $count++;
            }
        }

        return redirect()->route('admin.holidays')->with('success', "Successfully imported {$count} national holidays for {$year}.");
    }
}
