<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;

class MultithreadingRequest extends Command
{
    protected $signature = 'test:all';
    protected $description = 'Command description';

    private $get_users_url = 'http://61.190.70.38/ahpad-tpcxpj-web/dsfpg/household/querySbqkPage.do';
    private $get_user_info = 'http://61.190.70.38/ahpad-tpcxpj-web/dsfpg/household/getHouseHoldDetail.do';

    private $villages;

    public $client;
    public $cookieJar;

    public $search = '孙龙';

    public function __construct()
    {
        $this->client = new Client();

        $jar = new CookieJar();
        $domain = '61.190.70.38';
        $cookies = [
            'JSESSIONID' => 'ADDFFC7927C2427373D7DE3C13EB38EE;JSESSIONID=D12B9FE0B1180F9E11622870422BC8FD.tomcat8080',
            'Hm_lvt_b7ef9b48f3cf9ae1761276b4aaffb963' => 1512830506,
        ];
        $this->cookieJar = $jar->fromArray($cookies, $domain);
        parent::__construct();
    }

    public function handle()
    {
        $arr = ['05' => 1, '09' => 3, '16' => 3];
        //$arr = ['02','03','04','05','06','07','08','09','10','11','12','13','14','15','16'];
        foreach ($arr as $index => $item) {
            $request = $this->client->request('POST', $this->get_users_url, [
                'cookies' => $this->cookieJar,
                'form_params' => [
                    'hlx' => $item,
                    'currentPageNo' => 1,
                    'pageSize' => 500,
                    'province' => 340000000000,
                    'city' => 340100000000,
                    'county' => 340121000000,
                    'town' => "3401210{$index}000"// 城镇
                ]
            ]);
            $users = json_decode($request->getBody()->getContents());
            foreach ($users->data->result as $user) {
                $this->get_user_info($user);
            }
        }

        $this->write();
    }

    private function get_user_info($user)
    {
        if (!is_null($user)) {
            $uri = $this->get_user_info . '?pkhId=' . $user->sbqkId . '&hlx=' . 3;
            $request = $this->client->request('GET', $uri, [
                'cookies' => $this->cookieJar,
            ]);

            $userInfo = json_decode($request->getBody()->getContents())->data;
            if (str_contains($userInfo->tbr, $this->search) || str_contains($userInfo->pgdcr, $this->search)) {
                $this->villages["$userInfo->userName$userInfo->villageName"][] = $userInfo;
            }

        }
    }

    public function write()
    {
        foreach ($this->villages as $index => $userInfos) {
            Storage::append('file.txt', "村名：$index 共 " . count($userInfos) . "户");
            foreach ($userInfos as $userInfo) {
                // 脱贫户 是调查日期
                // 拟脱贫户是评估调查日期
                if ($userInfo->hlx == 3) {
                    $dcrq = str_replace('-', '.', $userInfo->dcrq);
                } else if ($userInfo->hlx == 1) {
                    $dcrq = str_replace('-', '.', $userInfo->pgdcsj);
                }
                $tmp = "{$userInfo->holdName}, 证件号:{$userInfo->aab004}，家住{$userInfo->organized}，家庭有{$userInfo->hjrks}口人，共同生活的有{$userInfo->gxkzrks}口人，属于{$userInfo->holdAttrName}户。{$userInfo->mainReasonName}致贫，{$userInfo->jdlksjName}建档立卡。{$userInfo->tpnd} 年脱贫，脱贫后政府安排 {$userInfo->zrls->bfzrrxm} 为该农户的帮扶联系人，帮扶联系人全年上门次数为{$userInfo->zrls->bfzrrsmcsName}。 通过走访调查，该贫困户：脱贫时人均纯收入为 {$userInfo->bcbz->bndjtsr} 元，目前家庭人均纯收入为 {$userInfo->bcbz->bndjtsr}。帮扶措施到位，对帮扶联系人满意，对国家帮扶措施满意。";
                Storage::append('1.txt', $tmp);
                Storage::disk('custom')->put("2017\\$dcrq\\$userInfo->holdName\\{$userInfo->holdName}户总结.txt", $tmp);
            }
        }
    }

}
