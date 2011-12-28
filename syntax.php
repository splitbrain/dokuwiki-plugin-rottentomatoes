<?php
/**
 * DokuWiki Plugin rottentomatoes (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <andi@splitbrain.org>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'syntax.php';
require_once dirname(__FILE__).'/template.php';

class syntax_plugin_rottentomatoes extends DokuWiki_Syntax_Plugin {
    private $apibase = 'http://api.rottentomatoes.com/api/public/v1.0/';

    public function getType() {
        return 'substition';
    }

    public function getPType() {
        return 'block';
    }

    public function getSort() {
        return 300;
    }

    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{rt>[^\}]+\}\}',$mode,'plugin_rottentomatoes');
    }

    public function handle($match, $state, $pos, &$handler){
        $data = array();

        // parse syntax
        $match = substr($match,5,-2);
        list($query,$options) = explode('?',$match,2);
        $query = trim($query);
        parse_str($options,$options);
        $data = array_merge($data,$options);

        // sanitize inputs
        $data['result'] = max((int) $data['result'], 1);
        $data['rate']   = max((int) $data['rate'], 0);
        $data['stars']  = max((int) $data['stars'], 0);
        if($data['stars'] > 5) $data['stars'] = 5;
        if($data['stars']) $data['rate'] = $data['stars']*20;
        if($data['rate'] > 100) $data['rate'] = 100;
        if(!preg_match('/^(left|right|center)$/',$data['align'])){
            $data['align'] = 'left';
        }
        $data['tpl'] = trim(preg_replace('/[^\w]+/','',$data['tpl']));
        if(!$data['tpl']) $data['tpl'] = 'thumb';

        // prepare API data
        if(substr($query,0,3) == 'id:'){
            $data['query']  = (int) substr($query,3);
            $data['type']   = 'id';
            $data['base']   = 'movies/'.$data['query'];
            $data['params'] = array();
        }elseif(substr($query,0,5) == 'imdb:'){
            $data['query']  = ltrim(substr($query,5),'t');
            $data['type']   = 'imdb';
            $data['base']   = 'movie_alias';
            $data['params'] = array(
                                'type' => 'imdb',
                                'id'   => $data['query']
                              );
        }else{
            $data['query']  = $query;
            $data['type']   = 'search';
            $data['base']   = 'movies';
            $data['params'] = array(
                                'q' => $data['query'],
                                'page_limit' => 1,
                                'page' => $data['result']
                              );
        }


        return $data;
    }

    public function render($mode, &$R, $data) {
        if($mode != 'xhtml') return false;

        $json = $this->apicall($data['base'],$data['params']);

        if($json && $data['type'] == 'search' && isset($json['movies'][0])){
            $json = $this->apicall('movies/'.$json['movies'][0]['id'], array());
        }elseif($json && $data['type'] == 'imdb' && isset($json['id'])){
            $json = $this->apicall('movies/'.$json['id'], array());
        }


        $R->doc .= '<div class="rottentomatoes rottentomatoes_'.$data['align'].'">';
        if(!$json){
            $R->doc .= 'Failed to get movie data';
        }else{
            $tpl = new rottentomatoes_template($json,$data);
            $R->doc .= $tpl->load($data['tpl']);

#            $R->doc .= '<img src="'.ml($json['posters']['thumbnail']).'" />';
        }
        $R->doc .= '</div>';


        return true;
    }


    /**
     * Execute a RottenTomatoes API call with caching
     */
    private function apicall($base, $params){
        $url = $this->apibase.$base.'.json';
        $params['apikey'] = $this->getConf('apikey');
        $url .= '?'.buildURLparams($params,'&');

        $json = new JSON(JSON_LOOSE_TYPE);

        $cache = getCacheName($url,'.rottentomatoes');
        if(file_exists($cache)) {
            return $json->decode(file_get_contents($cache));
        }

        // no cache, yet. download
        $http = new DokuHTTPClient();
        $data = $http->get($url);
        if(!$data) return false;
        $dec  = $json->decode($data);
        if(!$dec) return false;

        // store cache
        file_put_contents($cache, $data);
        return $dec;
    }

}

// vim:ts=4:sw=4:et:
