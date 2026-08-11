<?php

namespace Database\Seeders;

use App\Models\LibraryArticle;
use App\Models\LibraryCategory;
use Illuminate\Database\Seeder;

class LibrarySyriaContentSeeder extends Seeder
{
    public function run(): void
    {
        $cat = LibraryCategory::where('title->ar', 'سوريون في أوروبا')->first();
        if (!$cat) {
            $cat = LibraryCategory::create([
                'title' => [
                    'ar' => 'سوريون في أوروبا',
                    'en' => 'Syrians in Europe',
                    'tr' => 'Avrupa\'daki Suriyeliler',
                ],
            ]);
        }

        $articles = [
            [
                'title' => [
                    'ar' => 'التكيف النفسي بعد الهجرة',
                    'en' => 'Psychological adaptation after migration',
                    'tr' => 'Göç sonrası psikolojik uyum',
                ],
                'body' => [
                    'ar' => 'الهجرة تطرح تحديات نفسية واجتماعية. من الطبيعي الشعور بالحنين والقلق. ابدأ ببناء روتين يومي بسيط، وتواصل مع مجتمعك المحلي، ولا تتردد في طلب الدعم المهني عند الحاجة.',
                    'en' => 'Migration brings psychological and social challenges. Homesickness and anxiety are normal. Build a simple daily routine, connect with your local community, and seek professional support when needed.',
                    'tr' => 'Göç psikolojik ve sosyal zorluklar getirir. Özlem ve kaygı normaldir. Basit bir günlük rutin oluşturun, yerel topluluğunuzla bağ kurun ve gerektiğinde profesyonel destek alın.',
                ],
                'type' => 'article',
                'duration' => '6 min',
                'tags' => ['syria', 'europe', 'diaspora', 'adaptation'],
            ],
            [
                'title' => [
                    'ar' => 'الدعم النفسي للاجئين: حقوقك وخياراتك',
                    'en' => 'Mental health support for refugees: your rights and options',
                    'tr' => 'Mülteciler için ruh sağlığı desteği: haklarınız ve seçenekleriniz',
                ],
                'body' => [
                    'ar' => 'في أغلب دول الاتحاد الأوروبي تتوفر خدمات صحة نفسية مجانية أو مدعومة للاجئين. اسأل مراكز الاستقبال أو المنظمات المحلية عن برامج الدعم باللغة العربية.',
                    'en' => 'Most EU countries offer free or subsidized mental health services for refugees. Ask reception centers or local NGOs about Arabic-language support programs.',
                    'tr' => 'AB ülkelerinin çoğunda mülteciler için ücretsiz veya destekli ruh sağlığı hizmetleri vardır. Arapça destek programları için karşılama merkezlerine veya STK\'lara danışın.',
                ],
                'type' => 'article',
                'duration' => '5 min',
                'tags' => ['syria', 'europe', 'refugee', 'rights'],
            ],
            [
                'title' => [
                    'ar' => 'الحفاظ على الهوية بين ثقافتين',
                    'en' => 'Preserving identity between two cultures',
                    'tr' => 'İki kültür arasında kimliği korumak',
                ],
                'body' => [
                    'ar' => 'يمكنك الانتماء لثقافتين دون تضحية. شارك تقاليدك مع أصدقائك الجدد، وتعلّم عن المجتمع المضيف بفضول لا بخوف. التوازن يقلل الشعور بالانقسام الداخلي.',
                    'en' => 'You can belong to two cultures without sacrificing either. Share your traditions with new friends and learn about the host society with curiosity, not fear. Balance reduces inner conflict.',
                    'tr' => 'Her iki kültüre de ait olabilirsiniz. Geleneklerinizi yeni arkadaşlarınızla paylaşın ve misafir toplumu korkuyla değil merakla öğrenin. Denge iç çatışmayı azaltır.',
                ],
                'type' => 'tip',
                'duration' => '4 min',
                'tags' => ['syria', 'europe', 'identity', 'diaspora'],
            ],
        ];

        foreach ($articles as $article) {
            LibraryArticle::updateOrCreate(
                [
                    'category_id' => $cat->id,
                    'title->ar' => $article['title']['ar'],
                ],
                $article + ['category_id' => $cat->id, 'active' => true]
            );
        }
    }
}
