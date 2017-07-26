<?php


class RaidBotsHelper
{
    // Получает инфу из армори
    public function  getCharacterInfo ($region, $realm, $characterName){
        $ch = curl_init("https://www.raidbots.com/wowapi/character/". $region ."/ " .$realm ."/". urlencode($characterName));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    // Отправка запроса на генерацию статов. Заполнит simId - id отчета
    public function sendRequest($region, $realm, $characterName, $characterData){
        $data = json_encode(array(
            'advancedInput' => "",
            'apl' => "",
            'armory' =>['region'=> $region, 'realm'=> $realm, 'name'=> $characterName],
            'baseActorName' => $characterName,
            'character' => $characterData,
            'email' => "",
            'enemyCount' => 1,
            'enemyType' => "FluffyPillow",
            'fightLength' => 300,
            'fightStyle' => "Patchwerk",
            'frontendHost' => "www.raidbots.com",
            'frontendVersion' => "537d9863e1d288d9848d48776fce994034051b82",
            'gearsets' => [],
            'iterations' => 10000,
            'ptr' => false,
            'relics' => [],
            'reportName' => "Stat Weights",
            'sendEmail' => false,
            'simcItems' => [],
            'simcVersion' => "weekly",
            'spec' => $characterData->{'talents'}[0]->{'talents'}[0]->{'spec'}->{'name'},
            'talentSets' => [],
            'text' => "",
            'type' => "stats"),JSON_UNESCAPED_UNICODE);

        if( $curl = curl_init() ) {
            curl_setopt($curl, CURLOPT_URL, 'https://www.raidbots.com/sim');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json; charset=utf-8',
                    'Content-Length: ' . strlen($data))
            );

            curl_setopt($curl, CURLOPT_POSTFIELDS, ($data));
            $response = json_decode(curl_exec($curl));
            curl_close($curl);
            return $response->{"simId"};
        } else {
            return '';
        }
    }

    // базовая функция на генерацию весов
    public  function generateStatsWeights($region, $realm, $characterName){
        $characterData = json_decode($this->getCharacterInfo($region, $realm, $characterName));

        if ($characterData->{'status'}) {
            return $characterData->{'reason'};
        } else {
            return $this->sendRequest($region, $realm, $characterName, $characterData);
        }
    }

    // функция для проверки готов ли отчет. позвращает понятно что. когда position = 1, то значит вызывать больше не нужно
    public function checkJob ($report_id = false){
        if ($report_id) {
            $ch = curl_init("https://www.raidbots.com/job/" . $report_id);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            $response = json_decode(curl_exec($ch));
            curl_close($ch);
            return array(
                'status' => 'error',
                'total' => $response->{'queue'}->{'total'},
                'position' => $response->{'queue'}->{'position'},
            );
        } else {
            return array(
                'total' => '0',
                'position' => '0',
                'status' => 'error'
            );
        }
    }

    public function setPawnSting($data){
        $weights = $data->{'sim'}->{'players'}[0]->{'scale_factors'};
        $class = $data ->{'simbot'}->{'meta'}->{'charClass'};
        $result = '( Pawn: v1: "'. $data->{'sim'}->{'players'}[0]->{'name'} . '-' .
            $data ->{'simbot'}->{'meta'}->{'spec'} . '":Class='. $class . ', Spec='.
            $data ->{'simbot'}->{'meta'}->{'spec'};
        foreach ($weights as $key => $value){
            switch ($key){
                case 'Int':{
                    $result = $result. ',Intellect='. round($value, 2);
                    break;
                }
                case 'Crit': {
                    $result = $result. ',CritRating='. round($value, 2);
                    break;
                }
                case 'Haste':{
                    $result = $result. ',HasteRating='. round($value, 2);
                    break;
                }
                case 'Mastery':{
                    $result = $result. ',MasteryRating='. round($value, 2);
                    break;
                }
                case 'Vers':{
                    $result = $result. ',Versatility='. round($value, 2);
                    break;
                }
                case 'Agi': {
                    $result = $result. ',Agility='. round($value, 2);
                    break;
                }
                case 'Str': {
                    $result = $result. ',Strength='. round($value, 2);
                    break;
                }
            }
        }
        $result = $result. ')';
        return $result;
    }

    public function getSortedWeights($weights) {
        $arr = (array) $weights;
        arsort($arr);
        unset($arr['SP']);
        foreach ($arr as $key => $value){
            $arr[$key] = round($value, 2);
        }
        return $arr;
    }

    // функция которая возвращает результаты симуляции. вервет дпс и весы
    public function getReport($report_id = false){
        if ($report_id) {
            $ch = curl_init("https://www.raidbots.com/reports/" . $report_id . "/data.json");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            $response = json_decode(curl_exec($ch));
            curl_close($ch);
            if ($response->{'error'} || $response == null) {
                return array(
                    'status' => 'error',
                    'dps' => '0',
                    'weights' => '0',
                    'description'=>'Ошибка в отчете либо отчет  еще не готов'
                );
            } else {
                if (strcasecmp($response->{'simbot'}->{'meta'}->{'role'}, 'dps') == 0){
                    return array(
                        'status' => 'success',
                        'dps' => $response->{'sim'}->{'players'}[0]->{'collected_data'}->{'dps'}->{'mean'},
                        'weights' => $this->getSortedWeights($response->{'sim'}->{'players'}[0]->{'scale_factors'}),
                        'report_id' => $report_id,
                        'link' => 'https://www.raidbots.com/simbot/report/' . $report_id,
                        'pawn' => $this->setPawnSting($response),
                        'name' => $response->{'sim'}->{'players'}[0]->{'name'}
                    );
                } else{
                    return array(
                        'status'=>'error',
                        'dps'=>'0',
                        'weights'=>'0',
                        'name' => $response->{'sim'}->{'players'}[0]->{'name'},
                        'report_id' => $report_id,
                        'description'=>'Отчет может быть сформирован только для дамагеров'
                    );

                }

            }
        } else {
            return array(
                'status'=>'error',
                'dps'=>'0',
                'weights'=>'0',
                'description'=>'Отсутствует идентификатор отчета'
            );
            }
    }
}

 //Проверка работоспособности.
//$helper = new RaidBotsHelper("eu", "borean-tundra", "Киррочка");
//$helper->generateStatsWeights();
////$helper->setPawnSting('dfd');
//$result = $helper->checkJob();
//
//while ($result['position'] > 1){
//    $result = $helper->checkJob();
//    sleep(2);
//}

//$result = $helper->getReport('v3FGgDXxaD9m72UoNbgkmU');

//while (strcasecmp($result['status'], 'success') != 0){
//    $result = $helper->getReport();
//    sleep(2);
//}
//var_dump($result);

