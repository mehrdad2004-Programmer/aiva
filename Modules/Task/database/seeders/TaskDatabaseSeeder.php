<?php

namespace Modules\Task\Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaskDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        $chatId = '100200300';

        foreach (['کاری', 'شخصی', 'خرید', 'سلامتی', 'مطالعه'] as $title) {
            DB::table('task_types')->updateOrInsert(
                ['title' => $title, 'chat_id' => $chatId],
                ['updated_at' => $now, 'created_at' => $now]
            );
        }

        foreach ([1 => 'در صف', 2 => 'انجام‌شده', 3 => 'حذف‌شده'] as $id => $title) {
            DB::table('task_status')->updateOrInsert(
                ['id' => $id],
                ['title' => $title, 'deleted_at' => null, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $samples = [
            ['بررسی گزارش فروش هفتگی', 'کاری', 'آمار فروش این هفته با هفته قبل مقایسه شود.', '1', 1],
            ['خرید میوه و مواد غذایی', 'خرید', 'لیست خرید قبل از خروج از خانه بررسی شود.', '1', 0],
            ['مطالعه فصل سوم کتاب', 'مطالعه', 'نکات مهم فصل در دفتر یادداشت ثبت شود.', '2', -1],
            ['رزرو وقت دندان‌پزشکی', 'سلامتی', 'برای معاینه دوره‌ای تماس گرفته شود.', '1', 2],
            ['ارسال نسخه نهایی قرارداد', 'کاری', 'نسخه امضاشده برای مشتری ارسال شود.', '2', -2],
            ['پیاده‌روی عصرگاهی', 'سلامتی', 'حداقل چهل دقیقه پیاده‌روی سریع.', '1', 0],
            ['تماس با خانواده', 'شخصی', 'برای هماهنگی دورهمی آخر هفته تماس بگیر.', '1', 1],
            ['مرتب کردن میز کار', 'شخصی', 'اسناد قدیمی بایگانی و میز خلوت شود.', '2', -3],
            ['آماده‌سازی ارائه جلسه', 'کاری', 'اسلایدهای گزارش ماهانه تکمیل شوند.', '1', 3],
            ['خرید هدیه تولد', 'خرید', 'یک هدیه مناسب و ساده انتخاب شود.', '1', 4],
            ['مرور لغات زبان', 'مطالعه', 'بیست لغت جدید همراه مثال مرور شود.', '2', -1],
            ['پرداخت قبض اینترنت', 'شخصی', 'پیش از پایان مهلت پرداخت انجام شود.', '1', 2],
            ['بررسی ایمیل‌های مهم', 'کاری', 'ایمیل‌های ستاره‌دار پاسخ داده شوند.', '1', 0],
            ['تمرین تنفس و مدیتیشن', 'سلامتی', 'ده دقیقه تمرین تنفس عمیق.', '2', -2],
            ['تهیه دفتر یادداشت', 'خرید', 'دفتر نقطه‌ای با جلد ساده تهیه شود.', '1', 5],
            ['بازبینی برنامه هفتگی', 'شخصی', 'اولویت‌های هفته آینده مشخص شوند.', '1', 3],
            ['مطالعه مستندات پروژه', 'مطالعه', 'بخش API و ساختار داده مرور شود.', '1', 1],
            ['ثبت هزینه‌های ماه', 'شخصی', 'رسیدها و پرداخت‌های ماه ثبت شوند.', '2', -4],
        ];

        foreach ($samples as $index => [$title, $type, $comments, $status, $dayOffset]) {
            DB::table('tasks')->updateOrInsert(
                ['title' => $title, 'chat_id' => $chatId],
                [
                    'type' => $type,
                    'comments' => $comments,
                    'status' => $status,
                    'deleted_at' => null,
                    'updated_at' => $now,
                    'created_at' => $now->copy()->subDays(count($samples) - $index),
                ]
            );

            $taskId = DB::table('tasks')
                ->where('title', $title)
                ->where('chat_id', $chatId)
                ->value('id');

            DB::table('reminder')->updateOrInsert(
                ['task_id' => $taskId],
                [
                    'chat_id' => $chatId,
                    'remind_at' => $now->copy()->addDays($dayOffset)->setTime(9 + ($index % 9), ($index % 4) * 15),
                    'deleted_at' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
