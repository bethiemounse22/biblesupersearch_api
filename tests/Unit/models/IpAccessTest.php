<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
//use Faker\Generator;
use App\Models\IpAccess;

class IpAccessTest extends TestCase {
    protected $default_limit;

//    public function __construct($name = null, array $data = array(), $dataName = '') {
//        parent::__construct($name, $data, $dataName);
//        $default_limit = env('DAILY_ACCESS_LIMIT', 1000);
//    }

    public function testDefaultLimit() {
        $default_limit = config('bss.daily_access_limit');
        $ip = $this->_fakeIp();
        $IP = IpAccess::findOrCreateByIpOrDomain($ip);

        $this->assertEquals($default_limit, $IP->getAccessLimit());
        $this->assertEquals(0, $IP->getDailyHits());

        $IP->incrementDailyHits();
        $this->assertEquals(1, $IP->getDailyHits());
        $this->assertFalse($IP->isLimitReached());

        for($hits = 2; $hits < $default_limit; $hits ++) {
            $IP->incrementDailyHits();
        }

        $this->assertFalse($IP->isLimitReached());
        $IP->incrementDailyHits();
        $this->assertTrue($IP->isLimitReached());
        $IP->delete();
    }

    public function testCustomLimit() {
        $ip = $this->_fakeIp();
        $IP = IpAccess::findOrCreateByIpOrDomain($ip);
        $limit = 20;
        $IP->limit = $limit;
        $IP->save();

        $this->assertEquals($limit, $IP->getAccessLimit());
        $this->assertEquals(0, $IP->getDailyHits());

        $IP->incrementDailyHits();
        $this->assertEquals(1, $IP->getDailyHits());
        $this->assertFalse($IP->isLimitReached());

        for($hits = 2; $hits < $limit; $hits ++) {
            $IP->incrementDailyHits();
        }

        $this->assertFalse($IP->isLimitReached());
        $IP->incrementDailyHits();
        $this->assertTrue($IP->isLimitReached());
        $IP->delete();
    }

    public function testDomainCustomLimit() {
        $ip = $this->_fakeIp();
        $IP = IpAccess::findOrCreateByIpOrDomain($ip, 'example.com');
        $limit = 125;
        $IP->limit = $limit;
        $IP->save();

        $this->assertEquals($limit, $IP->getAccessLimit());
        $this->assertEquals(0, $IP->getDailyHits());

        $IP->incrementDailyHits();
        $this->assertEquals(1, $IP->getDailyHits());
        $this->assertFalse($IP->isLimitReached());

        for($hits = 2; $hits < $limit; $hits ++) {
            $IP->incrementDailyHits();
        }

        $this->assertFalse($IP->isLimitReached());
        $IP->incrementDailyHits();
        $this->assertTrue($IP->isLimitReached());
        $IP->delete();
    }

    public function testHostParsing() {
        $hosts = array(
            ['https://www.example.com/bible-search', 'example.com'],
            ['http://example.com/bible-search', 'example.com'],
            ['https://bible.example.com', 'bible.example.com'],
            ['https://bible.example.com/', 'bible.example.com'],
            ['http://search.bible.example.com/page/1', 'search.bible.example.com'],
            ['https://example.com/bible/?biblesupersearch_ingerface=Classic', 'example.com'],
            ['https://example.com/bible/search.html', 'example.com'],
            ['bib.example.com', 'bib.example.com'],
            ['http://study.search.bible.example.com/index.php', 'study.search.bible.example.com'],
            ['http://study.search.bible.example.com/index.php', 'study.search.bible.example.com'],
            ['example.org/grace-and-truth-came-through-jesus-christ-john-117/?fbclid=IwAR2bxtJmK9JKbhBY-Pznbf4NCGOjpsQ0ju6g05lXX6_XWIPj7h95tF4', 'example.org'],
            ['bib.example.com:7070/bible-tool/?customize_changeset_uuid=660a376f-ab87-41b1-b77a-489cae0a41d0', 'bib.example.com'],
            ['localhost', NULL],
            ['localhost:3333', NULL],
            ['http://top.prod.example.com/#/c/60fzq9yftx', 'top.prod.example.com'],
            ['http://top.prod.example.com#stuff', 'top.prod.example.com'],
            ['m.example.com/from=1000539d/s?word=supersearch%E5%96%B5%E5%96%B5%E5%96%B5&sa=ts_1&ts=6361105&t_kt=0&ie=utf-8&rsv_t=a6a1yI%252FfKJzP3GLcp13GTpYd5YT2WEGUGGcPoDu6ZHXMAE2pj5MMuUTitCaHKns&rsv_pq=10848949574623648659&ss=100&tj=1&rq=supersearch&rqlang=zh&rsv_sug4=', 'm.example.com'],
        );

        foreach($hosts as $h) {
            $domain = IpAccess::parseDomain($h[0]);
            $this->assertEquals($domain, $h[1]);
        }
    }

    public function testSameDomain() {
        $domain = 'http://www.example.com';

        $IP = IpAccess::findOrCreateByIpOrDomain($this->_fakeIp(), $domain);
        $this->assertEquals($IP->getAccessLimit(), config('bss.daily_access_limit'));

        $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'www.example.com';
        $this->assertEquals($IP->getAccessLimit(), 0);

        $IP->delete();
    }

    protected function _fakeIp() {
        // Ip addresses intentionally invalid
        return rand(256,999) . '.' . rand(1,255) . '.' . rand(1,255) . '.' . rand(1,255);
    }
}
