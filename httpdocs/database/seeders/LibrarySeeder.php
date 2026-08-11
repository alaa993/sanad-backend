<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LibraryCategory;
use App\Models\LibraryArticle;

class LibrarySeeder extends Seeder
{
    public function run(): void
    {
        $cat = LibraryCategory::create([
            'title' => ['ar'=>'الصحة النفسية','en'=>'Mental Health','tr'=>'Ruh Sağlığı']
        ]);

        LibraryArticle::create([
            'category_id' => $cat->id,
            'title' => ['ar'=>'كيف تتعامل مع القلق؟','en'=>'How to handle anxiety?','tr'=>'Anksiyete ile nasıl başa çıkılır?'],
            'body'  => ['ar'=>'نص عربي تجريبي...','en'=>'Sample English body...','tr'=>'Örnek Türkçe metin...'],
            'image' => null,
            'type'  => 'article',
            'duration' => '5 min',
            'active' => true,
        ]);
    }
}
