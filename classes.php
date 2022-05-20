<?
use Bitrix\Main;
use Bitrix\Main\Text\Converter;
use Bitrix\Main\Localization\Loc;
use Bitrix\Seo\RobotsFile;
use Bitrix\Seo\SitemapTable;
use Bitrix\Seo\SitemapEntityTable;
use Bitrix\Seo\SitemapIblockTable;
use Bitrix\Seo\SitemapForumTable;
use Bitrix\Seo\SitemapRuntimeTable;
use Bitrix\Main\Text\HtmlFilter;
use Bitrix\Main\Data\Cache;

class SitemapCostum {
    public $max_element = 5000;

    public function __construct($path)
    {
        $routes = $this->genRouters();
        if($path['extension'] !== 'xml') $this->error();

        $route = array_filter($routes, function($item) use ($path) {
            if($item['basename'] == $path['basename']) return $item;
        }, ARRAY_FILTER_USE_BOTH);
        if(!$route)  $this->error();
        $route = array_pop(array_reverse($route));
        switch($route['type']) {
            case 'index':
                $this->render_index($routes);
                return;
            case 'files':
                $this->render_files();
                return;
            case 'section':
                $this->render_section($route);
                return;
            case 'element':
                $this->render_element($route);
                return;
            default: $this->error();
        }
        // // echo '<pre>'; print_r($route); echo '</pre>';
        
        // $this->error();
        // echo '<pre>'; print_r($routes); echo '</pre>';
    }

    public function genRouters() {
        $route[] = ['basename' => 'sitemap.xml', 'type' => 'index'];
        $route[] = ['basename' => 'sitemap-files.xml', 'type' => 'files'];

        $dbSitemap = SitemapTable::getById(1);
        $arSitemap = $dbSitemap->fetch();
        $arSitemap['SETTINGS'] = unserialize($arSitemap['SETTINGS']);

        array_map(function($val, $key) use (&$route) {
            if($val === 'Y') $route[] = ['basename' => 'sitemap-section-'.$key.'.xml', 'type' => 'section', 'iblock' => $key];
        }, $arSitemap['SETTINGS']['IBLOCK_SECTION'], array_keys($arSitemap['SETTINGS']['IBLOCK_SECTION']));

        array_map(function($val, $key) use (&$route) {
            if($val === 'Y') {
                $this->optimization_element($key, $route);
            }
        }, $arSitemap['SETTINGS']['IBLOCK_ELEMENT'], array_keys($arSitemap['SETTINGS']['IBLOCK_ELEMENT']));

        //echo '<pre>'; print_r($route); echo '</pre>';

        return $route;
    }

    public function optimization_element($iblock, &$route) {
        $arFilter = Array("IBLOCK_ID"=>$iblock, "ACTIVE"=>"Y");
        $res_count = CIBlockElement::GetList(Array(), $arFilter, Array(), false, Array());
        if($res_count > $this->max_element) {
            $parts = ceil($res_count / $this->max_element);

            for($i = 1; $i <= $parts; $i++) {
                $route[] = ['basename' => 'sitemap-element-'.$iblock.'-part-'.$i.'.xml', 'type' => 'element', 'iblock' => $iblock, 'page' => $i];
            }
        } else {
            $route[] = ['basename' => 'sitemap-element-'.$iblock.'.xml', 'type' => 'element', 'iblock' => $iblock, 'page' => 1];
        }
    }

    public function error() {
        header("HTTP/1.0 404 Not Found");
        die();
    }

    public function render_element($route) {
        if(!$route['iblock']) $this->error();

        $site_url = 'https://'.$_SERVER['HTTP_HOST'];
        $xml_content = '';

        $res = CIBlockElement::GetList(
            array(),
            Array(
                "IBLOCK_ID" => intval($route['iblock']),
                "ACTIVE_DATE" => "Y",
                "ACTIVE" => "Y" ,
            ),
            false,
            Array("nPageSize"=>$this->max_element, 'iNumPage' => $route['page']),
            array(
            "ID",
            "NAME",
            "DETAIL_PAGE_URL",
        ));
        $quant = 0;
        while($ob = $res->GetNext())
        {
            $quant++;
            $xml_content .='
            <url>
                <loc>'.$site_url.$ob['DETAIL_PAGE_URL'].'</loc>
                <priority>0.5</priority>
            </url>
            ';
        }

        header('Content-Type: application/xml; charset=utf-8');

        echo '<?xml version="1.0" encoding="UTF-8"?>
        <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
            '.$xml_content.'
        </urlset>
        ';
        // echo '<pre>'; print_r($route); echo '</pre>';
    }
    
    public function render_section($route) {
        if(!$route['iblock']) $this->error();

        $site_url = 'https://'.$_SERVER['HTTP_HOST'];
        $xml_content = '';

        $res = CIBlockSection::GetList(
            array(),
            Array(
                "IBLOCK_ID" => intval($route['iblock']),
                "ACTIVE" => "Y" ,
            ),
            false,
            array(
            "ID",
            "NAME",
            "SECTION_PAGE_URL",
            "IBLOCK_ID",
        ));
        while($ob = $res->GetNext())
        {
            $xml_content .='
            <url>
                <loc>'.$site_url.$ob['SECTION_PAGE_URL'].'</loc>
                <priority>0.5</priority>
            </url>
            ';
        }

        header('Content-Type: application/xml; charset=utf-8');

        echo '<?xml version="1.0" encoding="UTF-8"?>
        <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
            '.$xml_content.'
        </urlset>
        ';
        // echo '<pre>'; print_r($route); echo '</pre>';
    }

    public function render_files() {
        $array_pages = include(__DIR__ . '/sitemap_static.php');
        $site_url = 'https://'.$_SERVER['HTTP_HOST'];

        $xml_content = '';
        foreach($array_pages as $v)
        {
            $xml_content .='
            <url>
                <loc>'.$site_url.$v['URL'].'</loc>
                <priority>'.$v['PRIORITY'].'</priority>
            </url>
            ';
        }
        header('Content-Type: application/xml; charset=utf-8');

        echo '<?xml version="1.0" encoding="UTF-8"?>
        <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
            '.$xml_content.'
        </urlset>
        ';

        // echo '<pre>'; print_r($array_pages); echo '</pre>';
    }

    public function render_index($routes) {
        header('Content-Type: application/xml; charset=utf-8');
        $site_url = 'https://'.$_SERVER['HTTP_HOST'];
        $xml_index = '';
        $loc = date('c', time());
        foreach($routes as $route) {
            if($route['type'] == 'index') continue;

            $xml_index .= '
            <sitemap>
                <loc>'.$site_url.'/sitemap/' . $route['basename'] . '</loc>
                <lastmod>'. $loc .'</lastmod>
            </sitemap>
            ';            
        }


        echo '<?xml version="1.0" encoding="UTF-8"?>
                <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    '.$xml_index.'
                </sitemapindex>
            ';
    }
}