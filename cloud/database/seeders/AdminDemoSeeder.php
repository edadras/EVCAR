<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AnprEvent;
use App\Models\Campaign;
use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\EdgeStation;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Organization;
use App\Models\Partner;
use App\Models\QrToken;
use App\Models\RepairOrder;
use App\Models\Reservation;
use App\Models\RfidCard;
use App\Models\Settlement;
use App\Models\SettlementItem;
use App\Models\Station;
use App\Models\SupportTicket;
use App\Models\SyncInbox;
use App\Models\SystemSetting;
use App\Models\Tariff;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vehicle;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminDemoSeeder extends Seeder
{
    /** @var array<int, array{name:string,city:string,province:string,lat:float,lng:float}> */
    private array $stationDefs = [
        ['name' => 'میدان آزادی', 'city' => 'تهران', 'province' => 'تهران', 'lat' => 35.699739, 'lng' => 51.338097],
        ['name' => 'برج میلاد', 'city' => 'تهران', 'province' => 'تهران', 'lat' => 35.7448, 'lng' => 51.3753],
        ['name' => 'نمایشگاه بین‌المللی', 'city' => 'تهران', 'province' => 'تهران', 'lat' => 35.7752, 'lng' => 51.4021],
        ['name' => 'صادقیه', 'city' => 'تهران', 'province' => 'تهران', 'lat' => 35.7219, 'lng' => 51.3347],
        ['name' => 'پارکینگ پاسداران', 'city' => 'تهران', 'province' => 'تهران', 'lat' => 35.7815, 'lng' => 51.4682],
        ['name' => 'حرم مطهر', 'city' => 'مشهد', 'province' => 'خراسان رضوی', 'lat' => 36.2881, 'lng' => 59.6156],
        ['name' => 'خیابان چهارباغ', 'city' => 'اصفهان', 'province' => 'اصفهان', 'lat' => 32.6546, 'lng' => 51.6680],
        ['name' => 'حافظیه', 'city' => 'شیراز', 'province' => 'فارس', 'lat' => 29.6296, 'lng' => 52.5256],
        ['name' => 'ایل‌گلی', 'city' => 'تبریز', 'province' => 'آذربایجان شرقی', 'lat' => 38.0962, 'lng' => 46.2919],
        ['name' => 'پل سفید', 'city' => 'اهواز', 'province' => 'خوزستان', 'lat' => 31.3183, 'lng' => 48.6706],
        ['name' => 'میدان شهدا', 'city' => 'کرمان', 'province' => 'کرمان', 'lat' => 30.2839, 'lng' => 57.0834],
        ['name' => 'امیرچخماق', 'city' => 'یزد', 'province' => 'یزد', 'lat' => 31.8974, 'lng' => 54.3569],
        // ایستگاه‌های بین‌راهی آزادراه تهران–قم–کاشان–اصفهان (برای مسیریاب برقی)
        ['name' => 'مجتمع خدماتی قم', 'city' => 'قم', 'province' => 'قم', 'lat' => 34.6416, 'lng' => 50.8746],
        ['name' => 'ورودی کاشان', 'city' => 'کاشان', 'province' => 'اصفهان', 'lat' => 33.9850, 'lng' => 51.4100],
        ['name' => 'مجتمع بین‌راهی نطنز', 'city' => 'نطنز', 'province' => 'اصفهان', 'lat' => 33.5130, 'lng' => 51.9160],
    ];

    private array $customerNames = [
        'رضا کاظمی', 'سارا احمدی', 'حسین نوری', 'مریم رضایی', 'علی محمدی',
        'فاطمه حسینی', 'مهدی جعفری', 'زهرا موسوی', 'امیر صادقی', 'نرگس کریمی',
        'پویا شریفی', 'لیلا باقری', 'کاوه مرادی', 'سمیرا عباسی', 'بهرام قاسمی',
    ];

    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@safircharge.ir'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'مدیر سیستم',
                'password' => Hash::make('Admin@123456'),
                'role' => UserRole::SuperAdmin,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        $staff = collect([
            ['name' => 'رضا کاظمی', 'email' => 'reza@safircharge.ir', 'role' => UserRole::FinancialManager],
            ['name' => 'مینا پورعلی', 'email' => 'mina@safircharge.ir', 'role' => UserRole::Support],
            ['name' => 'امیرحسین رستمی', 'email' => 'amir@safircharge.ir', 'role' => UserRole::StationOperator],
        ])->map(fn ($s) => User::updateOrCreate(
            ['email' => $s['email']],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $s['name'],
                'password' => Hash::make('Staff@123456'),
                'role' => $s['role'],
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        ));

        $tariff = Tariff::updateOrCreate(
            ['code' => 'TAR-STD'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'تعرفه استاندارد شهری',
                'pricing_model' => 'energy',
                'price_per_kwh' => 8500,
                'session_fee' => 15000,
                'is_default' => true,
                'status' => 'active',
            ]
        );

        Tariff::updateOrCreate(
            ['code' => 'TAR-PEAK'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'تعرفه اوج مصرف',
                'pricing_model' => 'energy',
                'price_per_kwh' => 11200,
                'session_fee' => 15000,
                'status' => 'active',
            ]
        );

        $partners = collect([
            ['code' => 'PTR-01', 'name' => 'پارکینگ پاسداران', 'city' => 'تهران', 'email' => 'partner@safircharge.ir'],
            ['code' => 'PTR-02', 'name' => 'شهرداری منطقه ۵', 'city' => 'تهران', 'email' => 'partner2@safircharge.ir'],
            ['code' => 'PTR-03', 'name' => 'مجتمع تجاری میلاد', 'city' => 'تهران', 'email' => 'partner3@safircharge.ir'],
        ])->map(fn ($p, $i) => Partner::updateOrCreate(
            ['code' => $p['code']],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $p['name'],
                'city' => $p['city'],
                'province' => 'تهران',
                'revenue_share_percent' => 70 - ($i * 5),
                'platform_fee_percent' => 30 + ($i * 5),
                'contact_email' => $p['email'] ?? strtolower($p['code']).'@partner.ir',
                'status' => 'active',
            ]
        ));

        $mainPartner = Partner::where('code', 'PTR-01')->first();
        if ($mainPartner) {
            $partnerUser = User::updateOrCreate(
                ['email' => 'partner@safircharge.ir'],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => 'مدیر '.$mainPartner->name,
                    'password' => Hash::make('Partner@123456'),
                    'role' => UserRole::Partner,
                    'partner_id' => $mainPartner->id,
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
            $mainPartner->update(['manager_user_id' => $partnerUser->id]);
        }

        Organization::updateOrCreate(
            ['code' => 'ORG-TAXI'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'تاکسی‌رانی تهران',
                'type' => 'taxi',
                'credit_limit' => 500_000_000,
                'status' => 'active',
            ]
        );

        $stations = collect();
        $chargerIndex = 1001;
        foreach ($this->stationDefs as $i => $def) {
            $code = 'ST-'.Str::upper(Str::random(4));
            $station = Station::updateOrCreate(
                ['name' => $def['name'], 'city' => $def['city']],
                [
                    'uuid' => (string) Str::uuid(),
                    'code' => $code,
                    'province' => $def['province'],
                    'address' => $def['name'].', '.$def['city'],
                    'latitude' => $def['lat'],
                    'longitude' => $def['lng'],
                    'partner_id' => $partners[$i % $partners->count()]->id,
                    'type' => 'public',
                    'status' => $i === 0 ? 'active' : ($i % 11 === 0 ? 'maintenance' : 'active'),
                    'wifi_ssid' => 'SafirCharge-'.Str::upper(Str::substr($code, -4)),
                    'local_hostname' => 'station-'.($i + 1).'.local',
                ]
            );

            $chargerCount = rand(2, 4);
            for ($c = 0; $c < $chargerCount; $c++) {
                $cpId = 'CP-'.$chargerIndex++;
                $online = $station->status === 'active' && rand(0, 10) > 1;
                $charger = Charger::updateOrCreate(
                    ['charge_point_id' => $cpId],
                    [
                        'uuid' => (string) Str::uuid(),
                        'station_id' => $station->id,
                        'vendor' => 'Safir',
                        'model' => $c % 2 ? 'DC Fast 120' : 'AC Type2 22',
                        'max_power_kw' => $c % 2 ? 120 : 22,
                        'connector_type' => $c % 2 ? 'CCS2' : 'Type2',
                        'is_online' => $online,
                        'last_heartbeat_at' => $online ? now()->subSeconds(rand(5, 120)) : now()->subHours(rand(1, 8)),
                        'status' => 'active',
                    ]
                );

                $statuses = ['Available', 'Charging', 'Preparing', 'Finishing', 'Faulted'];
                foreach ([1, 2] as $num) {
                    $ocpp = $online ? ($num === 1 && rand(0, 2) ? 'Charging' : 'Available') : 'Unavailable';
                    Connector::updateOrCreate(
                        ['charger_id' => $charger->id, 'connector_number' => $num],
                        [
                            'uuid' => (string) Str::uuid(),
                            'ocpp_status' => $ocpp,
                            'operational_status' => 'active',
                            'max_power_kw' => $charger->max_power_kw,
                            'status_updated_at' => now(),
                        ]
                    );
                }
            }

            $edgeToken = match ($i) {
                0 => 'dev-sync-token',
                1 => 'dev-sync-token-2',
                default => 'edge-'.$station->id,
            };

            EdgeStation::updateOrCreate(
                ['station_id' => $station->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'edge_code' => match ($i) {
                        0 => 'EDGE-AZADI-01',
                        1 => 'EDGE-MILAD-01',
                        default => 'EDGE-'.Str::upper(Str::substr($code, -6)),
                    },
                    'api_token_hash' => hash('sha256', $edgeToken),
                    'hostname' => $station->local_hostname,
                    'sync_status' => $station->status === 'active' ? 'online' : 'offline',
                    'last_heartbeat_at' => now()->subSeconds(rand(5, 180)),
                    'last_sync_at' => now()->subMinutes(rand(1, 30)),
                    'capabilities' => ['gsm', 'anpr', 'ocpp'],
                    'meta' => [
                        'anpr_cameras' => [
                            [
                                'id' => 'CAM-01',
                                'name' => 'دوربین ورودی — '.$station->name,
                                'rtsp_url' => '',
                                'role' => 'entry',
                                'enabled' => true,
                            ],
                        ],
                        'anpr' => [
                            'engine' => 'plpr_lite',
                            'plpr_url' => 'http://127.0.0.1:8502',
                            'min_confidence' => 0.92,
                            'auto_start_charging' => true,
                        ],
                    ],
                ]
            );

            $stations->push($station);
        }

        $customers = collect();
        foreach ($this->customerNames as $idx => $name) {
            $email = 'user'.($idx + 1).'@demo.ir';
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $name,
                    'phone' => '09'.rand(10, 99).rand(1000000, 9999999),
                    'password' => Hash::make('Demo@123456'),
                    'role' => UserRole::Customer,
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );

            $balance = rand(200_000, 5_000_000);
            $wallet = Wallet::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'balance' => $balance,
                    'bonus_balance' => rand(0, 200_000),
                    'status' => 'active',
                ]
            );

            $letters = ['ب', 'ج', 'د', 'س', 'ص', 'ط', 'ق', 'ل', 'م', 'ن'];
            if (! Vehicle::where('user_id', $user->id)->exists()) {
                Vehicle::create(
                    [
                        'user_id' => $user->id,
                        'plate_normalized' => sprintf('%02d%s%03d%02d', rand(10, 99), $letters[$idx % count($letters)], rand(100, 999), rand(10, 99)),
                        'battery_kwh' => [75, 60, 77, 64, 51][$idx % 5],
                        'uuid' => (string) Str::uuid(),
                        'plate_two' => (string) rand(10, 99),
                        'plate_letter' => $letters[$idx % count($letters)],
                        'plate_three' => (string) rand(100, 999),
                        'plate_province' => (string) rand(10, 99),
                        'brand' => ['تسلا', 'بی‌وای‌دی', 'هیوندای', 'کیا', 'MG'][$idx % 5],
                        'model' => 'EV',
                        'is_default' => true,
                    ]);
            }

            WalletTransaction::create([
                'uuid' => (string) Str::uuid(),
                'wallet_id' => $wallet->id,
                'type' => 'topup',
                'amount' => $balance,
                'balance_after' => $balance,
                'description' => 'شارژ اولیه کیف پول',
                'occurred_at' => now()->subDays(rand(5, 60)),
            ]);

            $customers->push($user);
        }

        $connectors = Connector::with('charger.station')->get();
        foreach ($connectors->take(12) as $connector) {
            $token = QrToken::firstOrCreate(
                ['connector_id' => $connector->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'station_id' => $connector->charger->station_id,
                    'charger_id' => $connector->charger_id,
                    'offline_capable' => true,
                    'status' => 'active',
                ]
            );
            $connector->update(['qr_token_uuid' => $token->uuid]);
        }

        RfidCard::withTrashed()->updateOrCreate(
            ['id_tag' => 'RFID-DEMO-001'],
            ['uuid' => (string) Str::uuid(), 'user_id' => $customers->first()->id, 'card_label' => 'کارت VIP', 'status' => 'active', 'issued_at' => now()->subMonths(2)]
        )->restore();

        for ($r = 2; $r <= 8; $r++) {
            RfidCard::withTrashed()->updateOrCreate(
                ['id_tag' => 'RFID-DEMO-'.str_pad((string) $r, 3, '0', STR_PAD_LEFT)],
                [
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $customers[$r % $customers->count()]->id,
                    'card_label' => 'کارت '.$r,
                    'status' => 'active',
                    'issued_at' => now()->subDays(rand(10, 90)),
                ]
            )->restore();
        }

        $connectors = Connector::with('charger.station')->get();
        $sessionCount = 0;
        for ($day = 29; $day >= 0; $day--) {
            $perDay = rand(4, 12);
            for ($s = 0; $s < $perDay; $s++) {
                $connector = $connectors->random();
                $station = $connector->charger->station;
                $user = $customers->random();
                $energy = round(rand(5, 65) + (rand(0, 99) / 100), 3);
                $cost = round($energy * 8500 + 15000, 2);
                $started = now()->subDays($day)->subHours(rand(0, 23))->subMinutes(rand(0, 59));
                $duration = rand(600, 7200);
                $isActive = $day === 0 && $s < 6;

                ChargingSession::create([
                    'uuid' => (string) Str::uuid(),
                    'station_id' => $station->id,
                    'charger_id' => $connector->charger_id,
                    'connector_id' => $connector->id,
                    'user_id' => $user->id,
                    'tariff_id' => $tariff->id,
                    'auth_method' => ['otp', 'rfid', 'qr', 'wallet'][rand(0, 3)],
                    'status' => $isActive ? 'active' : 'completed',
                    'started_at' => $started,
                    'stopped_at' => $isActive ? null : $started->copy()->addSeconds($duration),
                    'energy_kwh' => $isActive ? round($energy * 0.4, 3) : $energy,
                    'duration_seconds' => $isActive ? (int) ($duration * 0.4) : $duration,
                    'cost_amount' => $isActive ? round($cost * 0.4, 2) : $cost,
                    'synced_from_edge' => true,
                ]);
                $sessionCount++;
            }
        }

        foreach ($stations->take(6) as $station) {
            $user = $customers->random();
            Reservation::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'station_id' => $station->id,
                'status' => ['pending', 'confirmed', 'checked_in'][rand(0, 2)],
                'reserved_from' => now()->addHours(rand(1, 48)),
                'reserved_until' => now()->addHours(rand(49, 72)),
            ]);
        }

        $refundAmounts = [350_000, 480_000, 620_000, 850_000, 1_200_000];
        foreach (range(1, 7) as $t) {
            $isRefund = $t <= 3;
            $ticket = SupportTicket::updateOrCreate(
                ['ticket_number' => 'TK-'.(9200 + $t)],
                [
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $customers->random()->id,
                    'station_id' => $stations->random()->id,
                    'assigned_to' => $staff->random()->id,
                    'category' => $isRefund ? 'payment' : ['charger', 'app', 'network'][rand(0, 2)],
                    'priority' => $isRefund ? 'high' : ['low', 'normal', 'high'][rand(0, 2)],
                    'status' => $isRefund ? ['open', 'in_progress', 'open'][$t - 1] : ['open', 'in_progress', 'resolved'][rand(0, 2)],
                    'subject' => $isRefund
                        ? 'درخواست استرداد '.number_format($refundAmounts[$t - 1]).' تومان'
                        : 'مشکل شارژر شماره '.$t,
                    'body' => $isRefund ? 'شارژ ناقص انجام شد و مبلغ باید بازگردد.' : 'گزارش مشکل از سمت کاربر',
                ]);
            TicketMessage::updateOrCreate(
                ['support_ticket_id' => $ticket->id, 'message' => 'شارژر پس از اتصال قطع می‌شود.'],
                [
                    'user_id' => $ticket->user_id,
                    'is_staff_reply' => false,
                ]);
        }

        Campaign::updateOrCreate(
            ['name' => 'شارژ شبانه ۲۰٪ تخفیف'],
            ['uuid' => (string) Str::uuid(), 'type' => 'discount', 'status' => 'active', 'discount_percent' => 20, 'starts_at' => now()->subDays(5), 'ends_at' => now()->addDays(25)]
        );
        Campaign::updateOrCreate(
            ['name' => 'هدیه ۵۰ هزار تومانی عضویت'],
            ['uuid' => (string) Str::uuid(), 'type' => 'bonus', 'status' => 'active', 'bonus_amount' => 500_000, 'starts_at' => now()->subDays(10), 'ends_at' => now()->addDays(20)]
        );

        foreach ($customers->take(10) as $user) {
            UserNotification::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'channel' => 'in_app',
                'type' => 'session_complete',
                'title' => 'شارژ با موفقیت پایان یافت',
                'body' => 'جلسه شارژ شما تکمیل شد و مبلغ از کیف پول کسر گردید.',
                'sent_at' => now()->subHours(rand(1, 72)),
            ]);
        }

        $items = [
            ['sku' => 'CHG-DC-120', 'name' => 'شارژر DC 120kW', 'category' => 'charger', 'qty' => 24],
            ['sku' => 'CAB-CCS2-5M', 'name' => 'کابل CCS2 پنج متری', 'category' => 'cable', 'qty' => 86],
            ['sku' => 'RFID-READER', 'name' => 'کارت‌خوان RFID', 'category' => 'accessory', 'qty' => 42],
            ['sku' => 'CAM-ANPR', 'name' => 'دوربین پلاک‌خوان', 'category' => 'anpr', 'qty' => 18],
        ];
        foreach ($items as $item) {
            $inv = InventoryItem::updateOrCreate(
                ['sku' => $item['sku']],
                ['uuid' => (string) Str::uuid(), 'name' => $item['name'], 'category' => $item['category'], 'quantity_on_hand' => $item['qty'], 'status' => 'active']
            );
            InventoryMovement::create([
                'inventory_item_id' => $inv->id,
                'movement_type' => 'in',
                'quantity' => $item['qty'],
                'notes' => 'ورود اولیه انبار',
            ]);
        }

        $faultyCharger = Charger::where('is_online', false)->first() ?? Charger::first();
        RepairOrder::create([
            'uuid' => (string) Str::uuid(),
            'charger_id' => $faultyCharger?->id,
            'station_id' => $faultyCharger?->station_id,
            'status' => 'open',
            'priority' => 'high',
            'issue_description' => 'قطع ارتباط OCPP و عدم پاسخ‌دهی کانکتور ۱',
            'assigned_to' => $staff->last()->id,
            'opened_at' => now()->subDays(2),
        ]);

        foreach ($partners as $partner) {
            $gross = ChargingSession::whereHas('station', fn ($q) => $q->where('partner_id', $partner->id))
                ->where('status', 'completed')
                ->sum('cost_amount');
            $share = round($gross * ($partner->revenue_share_percent / 100), 2);
            $settlement = Settlement::updateOrCreate(
                ['settlement_number' => 'SET-'.$partner->code],
                [
                    'uuid' => (string) Str::uuid(),
                    'partner_id' => $partner->id,
                    'period_start' => now()->subMonth()->startOfMonth(),
                    'period_end' => now()->subMonth()->endOfMonth(),
                    'status' => 'approved',
                    'gross_revenue' => $gross,
                    'platform_fee' => $gross - $share,
                    'partner_share' => $share,
                    'energy_kwh' => ChargingSession::whereHas('station', fn ($q) => $q->where('partner_id', $partner->id))->sum('energy_kwh'),
                    'session_count' => ChargingSession::whereHas('station', fn ($q) => $q->where('partner_id', $partner->id))->count(),
                    'approved_at' => now()->subDays(5),
                ]
            );
            foreach (Station::where('partner_id', $partner->id)->get() as $st) {
                SettlementItem::updateOrCreate(
                    ['settlement_id' => $settlement->id, 'station_id' => $st->id],
                    [
                        'gross_revenue' => $st->chargingSessions()->sum('cost_amount'),
                        'partner_share' => round($st->chargingSessions()->sum('cost_amount') * 0.7, 2),
                        'energy_kwh' => $st->chargingSessions()->sum('energy_kwh'),
                        'session_count' => $st->chargingSessions()->count(),
                    ]
                );
            }
        }

        foreach ($stations->take(8) as $station) {
            $vehicle = Vehicle::inRandomOrder()->first();
            AnprEvent::create([
                'uuid' => (string) Str::uuid(),
                'station_id' => $station->id,
                'edge_detection_uuid' => (string) Str::uuid(),
                'plate_two' => $vehicle?->plate_two,
                'plate_letter' => $vehicle?->plate_letter,
                'plate_three' => $vehicle?->plate_three,
                'plate_province' => $vehicle?->plate_province,
                'plate_normalized' => $vehicle?->plate_normalized,
                'confidence' => round(rand(85, 99) / 100, 4),
                'vehicle_id' => $vehicle?->id,
                'user_id' => $vehicle?->user_id,
                'detected_at' => now()->subHours(rand(1, 48)),
                'meta' => [
                    'outcome' => $vehicle ? 'auto_start' : 'unknown_plate',
                    'camera_source' => 'CAM-01',
                ],
            ]);
        }

        $edge = EdgeStation::first();
        if ($edge) {
            SyncInbox::create([
                'event_uuid' => (string) Str::uuid(),
                'edge_station_uuid' => $edge->uuid,
                'event_type' => 'session.completed',
                'aggregate_type' => 'charging_session',
                'payload' => ['status' => 'ok'],
                'occurred_at' => now()->subMinutes(3),
                'received_at' => now()->subMinutes(2),
                'status' => 'processed',
                'processed_at' => now()->subMinute(),
            ]);
        }

        SystemSetting::updateOrCreate(
            ['group' => 'general', 'key' => 'platform_name'],
            ['value' => ['fa' => 'SafirCharge'], 'is_public' => true]
        );
        SystemSetting::updateOrCreate(
            ['group' => 'app', 'key' => 'privacy'],
            ['value' => ['fa' => 'SafirCharge اطلاعات حساب کاربری، سوابق شارژ، پلاک خودرو و موقعیت ایستگاه را صرفاً برای ارائه خدمات شارژ ذخیره می‌کند. داده‌ها مطابق قوانین جمهوری اسلامی ایران نگهداری و در صورت درخواست قانونی در اختیار مراجع ذی‌صلاح قرار می‌گیرد.'], 'is_public' => true]
        );
        SystemSetting::updateOrCreate(
            ['group' => 'app', 'key' => 'terms'],
            ['value' => ['fa' => 'استفاده از سامانه SafirCharge به منزله پذیرش تعرفه‌های اعلام‌شده، رعایت قوانین پارکینگ ایستگاه و مسئولیت نگهداری از کابل و کانکتور است. هرگونه سوءاستفاده از کارت RFID یا کیف پول منجر به مسدودسازی حساب می‌شود.'], 'is_public' => true]
        );
        SystemSetting::updateOrCreate(
            ['group' => 'sync', 'key' => 'engine'],
            ['value' => [
                'sync_interval_seconds' => 30,
                'batch_size' => 500,
                'retry_enabled' => true,
                'offline_queue_enabled' => true,
            ], 'is_public' => false]
        );

        $this->command?->info("Seeded: {$stations->count()} stations, {$sessionCount} sessions, {$customers->count()} customers");
        $this->command?->info('Admin: admin@safircharge.ir / Admin@123456');
    }
}
