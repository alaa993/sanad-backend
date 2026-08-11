
<?php
namespace Database\Seeders; use Illuminate\Database\Seeder; use Illuminate\Support\Facades\DB;
use App\Models\{Community, CommunityMember, CommunityPost, Article, Journal};
class CommunityArticleJournalSeeder extends Seeder {
  public function run(): void {
    $ownerId = DB::table('users')->value('id') ?? 1;
    $c1 = Community::firstOrCreate(['slug'=>'support-women'], [
      'name'=>['ar'=>'دعم النساء','en'=>'Women Support','tr'=>'Kadın Destek'],
      'about'=>['ar'=>'مساحة آمنة لدعم النساء','en'=>'Safe space for women','tr'=>'Kadınlar için güvenli alan'],
      'visibility'=>'public','owner_id'=>$ownerId,'category'=>'women','kind'=>'discussion'
    ]);
    CommunityMember::firstOrCreate(['community_id'=>$c1->id,'user_id'=>$ownerId],['role'=>'owner']);
    CommunityPost::firstOrCreate(['community_id'=>$c1->id,'author_id'=>$ownerId,'body'=>'أهلاً بكنّ في غرفة دعم النساء ❤️']);
    $c2 = Community::firstOrCreate(['slug'=>'teens-support'], [
      'name'=>['ar'=>'دعم المراهقين','en'=>'Teens Support','tr'=>'Genç Destek'],
      'about'=>['ar'=>'نقاشات للمراهقين بإشراف أخصائي','en'=>'Teens group moderated by specialist','tr'=>'Uzman moderasyonlu gençlik grubu'],
      'visibility'=>'public','owner_id'=>$ownerId,'category'=>'youth','kind'=>'discussion'
    ]);
    $c3 = Community::firstOrCreate(['slug'=>'trauma-support'], [
      'name'=>['ar'=>'دعم الصدمات','en'=>'Trauma Support','tr'=>'Travma Destek'],
      'about'=>['ar'=>'مجتمع للدعم النفسي بعد الصدمات','en'=>'Peer support after trauma','tr'=>'Travma sonrası destek'],
      'visibility'=>'public','owner_id'=>$ownerId,'category'=>'trauma','kind'=>'discussion'
    ]);
    CommunityMember::firstOrCreate(['community_id'=>$c3->id,'user_id'=>$ownerId],['role'=>'owner']);
    $c4 = Community::firstOrCreate(['slug'=>'diaspora-support'], [
      'name'=>['ar'=>'دعم الغربة','en'=>'Diaspora Support','tr'=>'Gurbet Destek'],
      'about'=>['ar'=>'مساحة للمغتربين واللاجئين','en'=>'Space for diaspora and refugees','tr'=>'Göçmenler için alan'],
      'visibility'=>'public','owner_id'=>$ownerId,'category'=>'diaspora','kind'=>'discussion'
    ]);
    CommunityMember::firstOrCreate(['community_id'=>$c4->id,'user_id'=>$ownerId],['role'=>'owner']);
    CommunityMember::firstOrCreate(['community_id'=>$c2->id,'user_id'=>$ownerId],['role'=>'owner']);
    CommunityPost::firstOrCreate(['community_id'=>$c2->id,'author_id'=>$ownerId,'body'=>'مرحبًا بالجميع! اكتبوا أول مشاركة.']);
    Article::firstOrCreate(['slug'=>'panic-steps-ar'], [
      'title'=>['ar'=>'خطوات سريعة لتهدئة نوبة الهلع','en'=>'Quick steps to calm a panic attack','tr'=>'Panik atağı yatıştırma adımları'],
      'body'=>['ar'=>'تنفّس ببطء، ركّز على خمس حواس، ذكّر نفسك بأن الشعور سيمر.','en'=>'Breathe slowly, use 5-4-3-2-1 technique.','tr'=>'Yavaş nefes alın, 5-4-3-2-1 tekniğini kullanın.'],
      'tags'=>['القلق','الصحة_النفسية'],'published'=>true
    ]);
    Journal::firstOrCreate(['user_id'=>$ownerId,'entry'=>'هذه أول مذكّرة شخصية لي مع سند.']);
  }
}
