<?php

namespace App\Infrastructure\Database\Seeders;

use PDO;

class s000003_vehicle_brands_seeder
{
    public function run(PDO $db): void
    {
        // 300+ Marcas Oficiales extraídas de VehiculoHelper.php
        $marcas = [
            'Abarth','Acadian','Alkon','Acura','Adenel','Adly','AICHI', 'AG','Agamar','AKT','AlfaRomeo','AmGeneral','American Motors','American Scooter','Andino',
            'Antillana Batelli','Apolo','Aprilia','Aro','Artesanal','Asia','Asia Motors','ASPT','AstecaCa','Aston Martin','Astra','Audi','Austin','Autogago',
            'Auverland','Ava','Avalon','Avance Marine','Avanti','Avila','Bajaj','Bateas de Occidente','Bateas Gerplap','Bateas JM','Bateas Lara','Bateas San Cristobal',
            'Baylainer','Bayliner','Barazabal','Baw','Bel','Belarus','Beltram','Benda','Benelli','Bentley','Bequivencaca','Bera','Beretta','Beri','Berto','Bertolini','Bertone','Bertram',
            'BlackJack','BlueBird','BMW','Boston Whaler','Brasil Services','Britanic','BrockWay','Bronx','Bronco','BRPSeaDoo','Brumoca','BUDC','Buick','Buque','BYD','CA Metalúrgica MGZ',
            'Cadillac','Cagiva','CFmoto','Can-Am','Canori','Caribe','Caroni','Carora','Carrocerias Marcos','Carrocerias nino','Carrocerias Chama','Carrocerias Cortez','Carrocerías y Bateas J. M. C.A',
            'Case','Caterpillar','Caterpillar DCLGP','Celimo','Century','Challenger','Chama','Chana','Chang','Changan','Changhe','Chanlin','CheckMate','Cheetah','Chema',
            'Chery','Chevrolet','Chevy','Cheymake','Chimana','Chituma','ChrisCraft','Chrysler','Citroen','Clark','Classics Motors','Colegint de Caracas','Commander',
            'Commer','Concord','Condor','Conquistador','Coppola','Coraline','Corvette','Cranchi','Crane','Custon','DInnocenzo','D´Silva','DaSilva','Dacia','Daewoo','DaewooPolska',
            'Dafu','Daihatsu','Daimler','Draco','DCMV','Dayun','Decaro','De Caro Motos','De Metal','De Soto','Delage','Deportivo','DeutzFahr','DFM','DFSK','Didi','Dite/Motorca','Divica',
            'Dodge','DodgeBrisa','Dongben','Dongfeng','Dors','Dorsay','Dragon','Ducati','Duka','Eager Beaver','Ebro','Edgarurist C.A.','Ekygo','El Gran GuaicaII',
            'ElTrebolCa','Empire','Empire Keeway','EmpireKeeway','Encava','Equivencaca','Erox','Escuda','Evinrude','Fabr.Extranjera','Fabricacion Nac','Fabri chassis CA','Fabrimonca',
            'Fadicaba','FAG','Falvocar','Fareara','Fargo','Favolcar','FAW','Feiying','Fengchi','Ferrari','FGWilson','Fiat','Fibro Nauta del Centro','Fica`s','Foday',
            'Fonta','Fontaine','Ford','Formaca','Forza','Foton','FRAB','FreeWays','Freightliner','Frenos de aire delc','Frueh','Fruehauf','Furgo Estacas','FY','FYM',
            'G.M.C','G.M.C.TRUCK','Gallegos','Galloper','GDAN','Geely','Gilera','GMC','Goitia','Grauli De La Trinidad','GreatDane','GreatWall','Grove','Guaica','Guasare',
            'Guerra','Guri','Guzzi','GWM','Hafei','Haima','Hamm','Haojiang','Haojin','Haojue','HarleyDavidson','Hatteras','Heibao','Hellstock','Hillman','Himoinsa','Hindustan',
            'Hino','Hino Motors de Venezuela CA','Highway','HJM','Honda','Hondai','Howo','Hongyi','Hualong','Hummer','Huoniao','Husqvarna','Hyosung','Hyster','Hyundai','HZ-Aceto','Ibranca','Icko',
            'Immeca','Immensa','Inbus','INCA','INCAVECA','Ind.MetalurgicaColon','Indianapoli','Indianapolis','Induaga','Indust.MetalHP','Infiniti','IngersollRand',
            'Inmeca','Inmecar','Innocenti','Instalme','Intermarine','Intermecca','Internacional','International','Intorca','Intraveca','Invermetal','Italika','Isuzu','Iveco',
            'Iveco-pegaso','Jac','Jaguar','Jeep','Jetour','Jialing','Jianshe','Jieda','Jinbei','Jinlun','JMC','Jobe','JohnDeere','Juve','JV Parts CA','Kama','Kadi','Kamaz','Kato','Kawasaki','Kawuasuki',
            'Keeway','Kendor','Kenworth','Kholler','Kia','Kioto','Kitomi','Komatsu','Koraca','Kremezis','Kripton','KTC','KTM','Kvane','Kymco','Kyoto','Kypor','Lada',
            'Lamborghini','Lamicar','Lancer.LS','Lancia','LandRover','Landolfo','Laval','LavenC.A','Ldv','Legacy','Legacy.','Leilong','LeomariferC.A','Leon','Lepard','Lexus',
            'LHT-B','Livan', 'Lifan','Lincoln','Linconl','Linhai','LinkBelt','LML','LODC','Loncin','Lotus','Luberowi','Mack','Maestro','Magnun','Mahindra','Maico','Makoo',
            'Malaguti','Manlio','Marauder','Marcopolo','Market','Maserati','Mastro','Matra','Max-','MaxMotor','MaxyPlus','Maxys','Maxus','Maybach','Maz','Mazda','Mazven','MC','MD',
            'MDHAOJIN','MDMD','Mercedes Benz','Mercruiser','Mercury','Meridional','Merlin','Metalico','MG','Mega','Miller','Mini','Mitsubishi','mishosuki','Moke','Motobosco','Molfetta',
            'Monon','Morgan','MotoTuning','Motomarket','MotostardeVzla','MotoTraxx','Mudan','MultibienesC.A','Multinautic','Muzin','NewHolland','NewJaguar','Nissan','Nomax','Novara',
            'Okio','Ombu','Oldsmobile','Opel','Orinoco','Pegaso','Peñero','Pershing','PeterBilt','Petibone','Peugeot','Piaggio','Pinzaguer','Piu','Plymouth','Polaris','Polifit','Pontiac','Porsche',
            'Princess','Profina','Promarine','Pronautica','Pumax','QiQingqi','Qingqi','Qipai','Ralvia','Ram','Rambler','Randon','Regal','Remer','Remiveca','Remolques',
            'RemolquesKremezis','RemolquesTiuna','Remorca','Remyveca','Renault','Reo','Retoño','Revasa','RF-USA','RinQuiw','Rio','RIVI','Rizzato','RofercaMotor','Roger\'s',
            'Roinca','Rolls-royce','Romanop','Rombauca','Rover','Rozo','RUDOX','RubherMarine','Saab','Sacin','Saic','SaicWuling','Saipa','Samuray','SanMarcos','SanLG','Santana',
            'Santos','Scania','Sanya','Scion','SeaDoo','SeaRay','Sea-Doo','Seacris','Seat','Semi','Senke','Serleca','Servicar','Shantui','Shenzheng By fIntnal','Shineray','Shuguang',
            'Silverton','Singlia','Skoda','Skygo','Smart','Sooter','Ssangyong','Star','Sterling','Stilus','Stri','Strick','Subaru','Sukida','Sukyama','Sullair','Sum','Sumo','Susuki',
            'Suzuki','SYM','T.M.I.','Talbot','TallerEulineHijos','C.A.','TallerValero','Tamelmeca','Tamoi','Tasca','Tata','Team','Teide','ThunderBird','Ticca','Titan','Tiuna',
            'Topaz','Toro','Tough','Toyota','Trailer','Trailer´sTachira','Trans','TranspymqhnosJr','Travesia','Traylers','Triton','Trim','Triumph','Trophy','Trynity','Tuning',
            'Tuohe','Tupamet','TVS','UM','Unico','Unistar','UnitedMotors','Utility','Van','Vanguard','Vaz','Veloce','Venchi','Venirauto','Vensun','Venuncia','Vento','Vermeer',
            'Verucci','Vespa','Viberti','Victory','VieiraYachts','voge','Volkswagen','Voltrailer','Volvo','Vstars','WabashNational','WackerNeuson','Waine','Wangye','Wartburg','Western star','White',
            'Willys','WitzcoChallenger','Wussi','WULING','Xama','XCMG','Xiangshuai','Xingfu','Xingyue','Yagusa', 'Yadea','Yamaha','Yamati','Yamatsu','Yamazaki','YASUKI','Yingang','Yiying','YR SANYA','Yuexin','YutongBus',
            'Zhejiang','Zhongneng','Zhongxing','ZN','Znen','Zongshen','Zorzi','Zotye','Zxmco','SM santillana'
        ];

        // Normalizar nombres únicos (trim, deduplicar)
        $uniqueMarcas = array_values(array_unique(array_filter(array_map('trim', $marcas))));

        $stmt = $db->prepare("
            INSERT INTO vehicle_brands (name) 
            VALUES (:name)
            ON DUPLICATE KEY UPDATE name = VALUES(name)
        ");

        $db->beginTransaction();
        try {
            foreach ($uniqueMarcas as $brandName) {
                if ($brandName !== '') {
                    $stmt->execute(['name' => $brandName]);
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
