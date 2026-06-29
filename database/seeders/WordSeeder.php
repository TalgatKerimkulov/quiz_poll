<?php

namespace Database\Seeders;

use App\Models\Word;
use Illuminate\Database\Seeder;

class WordSeeder extends Seeder
{
    public function run(): void
    {
        $words = array_merge(
            $this->a1(),
            $this->a2(),
            $this->b1(),
            $this->b2(),
        );

        foreach ($words as $word) {
            $model = Word::updateOrCreate(
                [
                    'source' => 'seed',
                    'source_key' => hash('sha256', implode('|', [$word[0], $word[1], $word[2]])),
                ],
                [
                    'term' => $word[0],
                    'locale' => 'en',
                    'level' => $word[2],
                    'part_of_speech' => $word[3] ?? null,
                    'is_active' => true,
                ],
            );
            $model->translations()->updateOrCreate(
                ['locale' => 'ru'],
                [
                    'text' => $word[1],
                    'status' => 'reviewed',
                    'source' => 'seed',
                    'reviewed_at' => now(),
                ],
            );
            if (isset($word[4])) {
                $model->examples()->updateOrCreate(
                    ['locale' => 'en', 'text' => $word[4]],
                    [
                        'translation_locale' => isset($word[5]) ? 'ru' : null,
                        'translation_text' => $word[5] ?? null,
                        'source' => 'seed',
                    ],
                );
            }
        }
    }

    protected function a1(): array
    {
        return [
            ['apple','яблоко','A1','noun'],['book','книга','A1','noun'],['water','вода','A1','noun'],['house','дом','A1','noun'],['car','машина','A1','noun'],
            ['work','работа','A1','noun'],['city','город','A1','noun'],['friend','друг','A1','noun'],['food','еда','A1','noun'],['school','школа','A1','noun'],
            ['family','семья','A1','noun'],['money','деньги','A1','noun'],['time','время','A1','noun'],['day','день','A1','noun'],['night','ночь','A1','noun'],
            ['road','дорога','A1','noun'],['phone','телефон','A1','noun'],['table','стол','A1','noun'],['chair','стул','A1','noun'],['window','окно','A1','noun'],
            ['door','дверь','A1','noun'],['teacher','учитель','A1','noun'],['student','студент','A1','noun'],['music','музыка','A1','noun'],['movie','фильм','A1','noun'],
            ['mother','мама','A1','noun'],['father','папа','A1','noun'],['child','ребёнок','A1','noun'],['name','имя','A1','noun'],['country','страна','A1','noun'],
            ['room','комната','A1','noun'],['bed','кровать','A1','noun'],['bag','сумка','A1','noun'],['pen','ручка','A1','noun'],['paper','бумага','A1','noun'],
            ['milk','молоко','A1','noun'],['bread','хлеб','A1','noun'],['egg','яйцо','A1','noun'],['coffee','кофе','A1','noun'],['tea','чай','A1','noun'],
            ['cat','кот','A1','noun'],['dog','собака','A1','noun'],['bird','птица','A1','noun'],['fish','рыба','A1','noun'],['tree','дерево','A1','noun'],
            ['sun','солнце','A1','noun'],['rain','дождь','A1','noun'],['snow','снег','A1','noun'],['shop','магазин','A1','noun'],['park','парк','A1','noun'],
            ['bus','автобус','A1','noun'],['train','поезд','A1','noun'],['plane','самолёт','A1','noun'],['street','улица','A1','noun'],['doctor','врач','A1','noun'],
            ['hospital','больница','A1','noun'],['hand','рука','A1','noun'],['head','голова','A1','noun'],['eye','глаз','A1','noun'],['foot','ступня','A1','noun'],
            ['big','большой','A1','adjective'],['small','маленький','A1','adjective'],['good','хороший','A1','adjective'],['bad','плохой','A1','adjective'],['new','новый','A1','adjective'],
            ['old','старый','A1','adjective'],['hot','горячий','A1','adjective'],['cold','холодный','A1','adjective'],['happy','счастливый','A1','adjective'],['sad','грустный','A1','adjective'],
            ['go','идти','A1','verb'],['come','приходить','A1','verb'],['see','видеть','A1','verb'],['eat','есть','A1','verb'],['drink','пить','A1','verb'],
            ['read','читать','A1','verb'],['write','писать','A1','verb'],['speak','говорить','A1','verb'],['listen','слушать','A1','verb'],['sleep','спать','A1','verb'],
            ['open','открывать','A1','verb'],['close','закрывать','A1','verb'],['buy','покупать','A1','verb'],['like','нравиться','A1','verb'],['want','хотеть','A1','verb'],
        ];
    }

    protected function a2(): array
    {
        return [
            ['market','рынок','A2','noun'],['weather','погода','A2','noun'],['question','вопрос','A2','noun'],['answer','ответ','A2','noun'],['language','язык','A2','noun'],
            ['lesson','урок','A2','noun'],['travel','путешествие','A2','noun'],['health','здоровье','A2','noun'],['story','история','A2','noun'],['ticket','билет','A2','noun'],
            ['station','станция','A2','noun'],['airport','аэропорт','A2','noun'],['holiday','праздник','A2','noun'],['meeting','встреча','A2','noun'],['message','сообщение','A2','noun'],
            ['problem','проблема','A2','noun'],['idea','идея','A2','noun'],['plan','план','A2','noun'],['price','цена','A2','noun'],['gift','подарок','A2','noun'],
            ['restaurant','ресторан','A2','noun'],['kitchen','кухня','A2','noun'],['bathroom','ванная','A2','noun'],['office','офис','A2','noun'],['company','компания','A2','noun'],
            ['job','работа','A2','noun'],['salary','зарплата','A2','noun'],['address','адрес','A2','noun'],['map','карта','A2','noun'],['key','ключ','A2','noun'],
            ['simple','простой','A2','adjective'],['difficult','сложный','A2','adjective'],['early','ранний','A2','adjective'],['late','поздний','A2','adjective'],['cheap','дешёвый','A2','adjective'],
            ['expensive','дорогой','A2','adjective'],['fast','быстрый','A2','adjective'],['slow','медленный','A2','adjective'],['clean','чистый','A2','adjective'],['dirty','грязный','A2','adjective'],
            ['strong','сильный','A2','adjective'],['weak','слабый','A2','adjective'],['interesting','интересный','A2','adjective'],['boring','скучный','A2','adjective'],['important','важный','A2','adjective'],
            ['learn','учить','A2','verb'],['teach','преподавать','A2','verb'],['ask','спрашивать','A2','verb'],['answer','отвечать','A2','verb'],['start','начинать','A2','verb'],
            ['finish','заканчивать','A2','verb'],['wait','ждать','A2','verb'],['help','помогать','A2','verb'],['call','звонить','A2','verb'],['pay','платить','A2','verb'],
            ['choose','выбирать','A2','verb'],['change','менять','A2','verb'],['try','пытаться','A2','verb'],['remember','помнить','A2','verb'],['forget','забывать','A2','verb'],
            ['bring','приносить','A2','verb'],['carry','нести','A2','verb'],['send','отправлять','A2','verb'],['receive','получать','A2','verb'],['meet','встречать','A2','verb'],
        ];
    }

    protected function b1(): array
    {
        return [
            ['experience','опыт','B1','noun'],['opportunity','возможность','B1','noun'],['decision','решение','B1','noun'],['environment','окружающая среда','B1','noun'],['community','сообщество','B1','noun'],
            ['relationship','отношения','B1','noun'],['education','образование','B1','noun'],['knowledge','знание','B1','noun'],['skill','навык','B1','noun'],['behavior','поведение','B1','noun'],
            ['purpose','цель','B1','noun'],['reason','причина','B1','noun'],['result','результат','B1','noun'],['advantage','преимущество','B1','noun'],['disadvantage','недостаток','B1','noun'],
            ['responsibility','ответственность','B1','noun'],['choice','выбор','B1','noun'],['challenge','вызов','B1','noun'],['support','поддержка','B1','noun'],['progress','прогресс','B1','noun'],
            ['confident','уверенный','B1','adjective'],['available','доступный','B1','adjective'],['necessary','необходимый','B1','adjective'],['similar','похожий','B1','adjective'],['different','разный','B1','adjective'],
            ['possible','возможный','B1','adjective'],['successful','успешный','B1','adjective'],['careful','осторожный','B1','adjective'],['useful','полезный','B1','adjective'],['common','обычный','B1','adjective'],
            ['improve','улучшать','B1','verb'],['develop','развивать','B1','verb'],['explain','объяснять','B1','verb'],['describe','описывать','B1','verb'],['compare','сравнивать','B1','verb'],
            ['suggest','предлагать','B1','verb'],['decide','решать','B1','verb'],['prepare','готовить','B1','verb'],['continue','продолжать','B1','verb'],['avoid','избегать','B1','verb'],
            ['include','включать','B1','verb'],['provide','предоставлять','B1','verb'],['create','создавать','B1','verb'],['increase','увеличивать','B1','verb'],['reduce','снижать','B1','verb'],
        ];
    }

    protected function b2(): array
    {
        return [
            ['achievement','достижение','B2','noun'],['assumption','предположение','B2','noun'],['awareness','осведомлённость','B2','noun'],['consequence','последствие','B2','noun'],['constraint','ограничение','B2','noun'],
            ['controversy','спор','B2','noun'],['efficiency','эффективность','B2','noun'],['evidence','доказательство','B2','noun'],['framework','структура','B2','noun'],['impact','влияние','B2','noun'],
            ['insight','понимание','B2','noun'],['perspective','точка зрения','B2','noun'],['priority','приоритет','B2','noun'],['requirement','требование','B2','noun'],['strategy','стратегия','B2','noun'],
            ['accurate','точный','B2','adjective'],['consistent','последовательный','B2','adjective'],['crucial','критически важный','B2','adjective'],['efficient','эффективный','B2','adjective'],['reliable','надёжный','B2','adjective'],
            ['assess','оценивать','B2','verb'],['clarify','прояснять','B2','verb'],['demonstrate','демонстрировать','B2','verb'],['emphasize','подчёркивать','B2','verb'],['evaluate','оценивать','B2','verb'],
        ];
    }
}
