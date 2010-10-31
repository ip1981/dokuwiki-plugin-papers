<?php
/**
 * DokuWiki Plugin papers (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Igor Pashev <pashev.igor@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');

global $conf;
if (!defined('PAPERS_DATADIR')) define('PAPERS_DATADIR', DOKU_INC . $conf['savedir'] . '/media/');

define('PLUGIN_SELF', dirname(__FILE__) . '/');

require_once (DOKU_INC . 'inc/parserutils.php');
require_once (DOKU_PLUGIN . 'syntax.php');
require_once (DOKU_PLUGIN . 'papers/bibtex.php');

class syntax_plugin_papers extends DokuWiki_Syntax_Plugin
{
    function getType() { return 'protected'; }

    function getPType() { return 'stack'; }

    function getSort() { return 102; }


    function connectTo($mode)
    {
        $this->Lexer->addEntryPattern('<bibtex(?=.*</bibtex>)', $mode, 'plugin_papers');
        $this->Lexer->addEntryPattern('<papers(.+?)(?=.*</papers>)', $mode, 'plugin_papers');
    }

    function postConnect()
    {
        $this->Lexer->addExitPattern('</bibtex>', 'plugin_papers');
        $this->Lexer->addExitPattern('</papers>', 'plugin_papers');
    }

    function handle($match, $state, $pos, &$handler)
    {
        switch ($state)
        {
            case DOKU_LEXER_ENTER :
                return array($state, array());
 
            case DOKU_LEXER_UNMATCHED :
                $bibtex = new BibtexParserGoga();
                $bibtex->read_text($match);
                $bibtex->select();
                $bibtex->sort();
                return array($state, $bibtex);
        
            case DOKU_LEXER_EXIT :
                return array($state, '');
        }
        return array();
    }

    function render($mode, &$renderer, $data)
    {
        if ($mode != 'xhtml') return false;
        list($state, $bibtex) = $data;
        switch ($state)
        {
            case DOKU_LEXER_ENTER: 
                break;

            case DOKU_LEXER_UNMATCHED:
                $renderer->doc .= $this->format_bibtex($bibtex);
                break;

            case DOKU_LEXER_EXIT :
                break;
        }
        return true;
    }

    function wikirender($text)
    {
        return p_render('xhtml', p_get_instructions($text), $info);
    }


    function format_bibtex(&$bibtex)
    {
        $res = '';
        $year = '';
        $year_prev = '';
        $type = '';
        $type_prev = '';
        $in_list = false;
        foreach ($bibtex->SELECTION as &$entry)
        {
            preg_match('/(\d{4})/u', $entry['year'], $matches);
            $year = $matches[1];
            if ($year < $this->getConf('year_min'))
                break;

            if ($year !== $year_prev)
            {
                if ($in_list)
                {
                    $res .= "</ol>\n";
                    $in_list = false;
                }

                $year_prev = $year;
                $type_prev = '';
                $res .= $this->wikirender('===== ' . $year . ' ' . $this->getLang('year') . '  =====');
            }

            $type = $entry['entry'];
            if ($type !== $type_prev)
            {
                if ($in_list)
                {
                    $res .= "</ol>\n";
                    $in_list = false;
                }

                $type_prev = $type;
                $res .= $this->wikirender('==== ' . $this->getLang($type) . '  ====');
            }

            if (!$in_list)
            {
                $res .= "<ol>\n";
                $in_list = true;
            }
            $res .= '<li class="level1"><div class="li" style="padding:0.3em;">' . $entry['html'];
            
            $links = array();
            foreach ($this->getConf('filetypes') as $type)
            {
                $file = $this->getConf('papers_ns') . '/' . $entry['id'] . '.' . mb_strtolower($type);
                if (file_exists(PAPERS_DATADIR . $file))
                {
                    $size = round(filesize(PAPERS_DATADIR . $file) / 1024) . ' ' . $this->getLang('KiB');
                    $links[] = '{{:' . preg_replace('/\//u', ':', $file) . "|$type $size}}";
                }
            }
            if (!empty($links))
            {
                $link_text = $this->wikirender('(' . implode(' | ', $links) . ')');
                $link_text = preg_replace('/<\/?p>/u', '', $link_text);
                $link_text = preg_replace('/\s+(\d+)\s+/u', '&nbsp;\1&nbsp;', $link_text);
                $res .= '<span class="noprint">' . $link_text . '</span>';
            }
            $res .=  "</div></li>\n";
        }


        $res .= "</ol>\n";
        return $res;
    }
}

// vim:ts=4:sw=4:et:enc=utf-8:

