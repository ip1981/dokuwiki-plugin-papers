<?php

class BibtexParser
{
    public $STRINGS = array(); // @STRING(matveev="В. И. Матвеев") -> 'matveev' => "В. И. Матвеев"
    protected $STRINGS_o = array(); // 'jetp' => 1 - to sort by journal importance
    public $ENTRIES = array();
    public $SELECTION = array();


    /*
     * Expand strings like 'gusarevich # " and " # matveev',
     * substituting 'gusarevich' and 'matveev'
     * from $STRINGS
     *
     */
    public function expand_string($str)
    {
        $chunks = preg_split('/\s*#\s*/', $str);
        $len = count($chunks);
        for ($i = 0; $i < count($chunks) ; $i++)
        {
            if (preg_match('/"(.*?)"/', $chunks[$i], $matches))
            {
                $chunks[$i] = $matches[1];
            }
            elseif (isset($this->STRINGS[$chunks[$i]])) // not !empty(), but isset() !
            {
                $chunks[$i] = $this->STRINGS[$chunks[$i]];
            }
        }

        $r = implode($chunks);
        return $r;
    }


    /*
     * Parse a line of BiBTeX data
     * and collect strings and bib-entries
     *
     */
    protected function parse_string($line)
    {
        static $bibent = '';
        static $string_no = 0;

        if (preg_match('/@STRING\s*\((.+?)\s*=\s*"(.*?)"\)/u', $line, $matches))
        {
            $this->STRINGS[$matches[1]] = $matches[2];
            $this->STRINGS_o[$matches[1]] = $string_no++;
        }
        elseif (preg_match('/@(\w+?)\s*\{\s*(\w+?)\s*,/u', $line, $matches))
        {   // TODO: Ignore wrong fields
            $bibent = $matches[2];
            $this->ENTRIES[$bibent]['entry'] = strtolower($matches[1]);
            $this->ENTRIES[$bibent]['id'] = strtolower($matches[2]);
            // e. g. $ENTRIES['pashev_2010_axiom']['entry'] = 'book'
        }
        elseif (preg_match('/(\w+?)\s*=\s*(.*?)\s*,?\s*$/u', $line, $matches))
        {
            $this->ENTRIES[$bibent][strtolower($matches[1])] = $matches[2];
            /* e. g.
             * $ENTRIES['pashev_2010_axiom']['year'] = 2010
             * $ENTRIES['pashev_2010_axiom']['numpages'] = 68
             * and so on...
             */
        }
    }


    /*
     * Read file line by line
     * and pass every line to parse_string()
     *
     */
    public function read_file($filename)
    {
        $handle = fopen($filename, 'rb');
        if ($handle)
        {
            while (!feof($handle))
            {
                $line = fgets($handle);
                $this->parse_string($line);
            }
            fclose($handle);
        }
    }

    /*
     * Read raw bibtex data (text)
     * and pass every line to parse_string()
     *
     */
    public function read_text($text)
    {
        if (!is_array($text))
        {
            $text = preg_split('/\n/u', $text);
        }
        foreach ($text as &$line)
        {
            $this->parse_string($line);
        }
    }

    public function expand_years()
    {
        $entries = array();    //  \/ - not a ref!
        foreach ($this->ENTRIES as $e)
        {
            if (empty($e['year']) && !empty($e['years'])
                && preg_match('/(\d{4})\D+?(\d{4})/', $e['years'], $m))
            {
                for ($y = $m[1]; $y <= $m[2]; $y++)
                {
                    $e['year'] = $y;
                    $entries[] = $e;
                }
            }
        }
        $this->ENTRIES = array_merge($this->ENTRIES, $entries);
    }

    /*
     * $SELECTION keeps only references
     * to BiBTeX entries stored in $ENTRIES
     *
     * If entry is selected for the first time
     * a new field is added - formatted HTML -
     * which should be ready to display
     *
     */
    public function select($search = array())
    {
        foreach ($this->ENTRIES as &$entry)
        {
            $select = true;
            foreach ($search as $key => $value)
            {
                $key = strtolower($key);
                if (!empty($entry[$key]) && !preg_match('/' . $value . '/u', $entry[$key]))
                {
                    $select = false;
                    break;
                }
            }
            if ($select)
            {
                if (empty($entry['html']))
                {
                    $entry['html'] = $this->format($entry);
                }
                $this->SELECTION[] = $entry;
            }
        }
    }

    public function latex2html($text)
    {
        $text = preg_replace('/([^\\\\])~/u', '\\1&nbsp;', $text);
        $text = preg_replace('/<<(.*?)>>/u',    '«\1»',    $text);
        $text = preg_replace('/(\d+)\s*-{1,3}\s*(\d+)/u',  '\1&ndash;\2',  $text);
        $text = preg_replace('/---/u',  '&mdash;',  $text);
        $text = preg_replace('/--/u',  '&ndash;',  $text);
        $text = preg_replace('/\^\{(.+?)\}/u',  '<sup>\1</sup>',  $text);
        $text = preg_replace('/_\{(.+?)\}/u',  '<sub>\1</sub>',  $text);
        $text = preg_replace('/\$(.+?)\$/u',  '<tt>\1</tt>',  $text);
        
        $text = preg_replace('/\{(.*?)\}/u',  '\1',  $text);

        return $text;
    }


    /*
     * Format one BiBTeX entry in HTML
     *
     */
    public function format($entry)
    {
        $res = 'This is an abstract method';
        return $res;
    }


    /*
     * Export sorted BiBTeX.
     * Do not use after $this->expand_years()
     *
     */
    public function export()
    {
        $res = '';
        $this->sort();
        foreach ($this->STRINGS as $key => $value)
        {
            $res .= '@STRING (' . $key . '="' . $value . '")' . "\n";
        }

        $res .= "\n\n";
        
        foreach ($this->ENTRIES as $ent)
        {
            $res .= '@' . mb_strtoupper($ent['entry']) . ' {' . $ent['id'] . ",\n";
            $fields = array();
            foreach($ent as $key => $value)
            {
                // Skip our special fields
                if (!in_array($key, array('id', 'entry')))
                    $fields[] = mb_strtoupper($key) . '=' . $value;
            }
            $res .= implode(",\n", $fields);
            $res .= "\n}\n\n";
        }
        return $res;
    }
}


/*
 * Example class for very special purpose
 *
 */
class BibtexParserTeam extends BibtexParser
{
    protected $entry;

    protected $I18N = array(
            'p.'    => array('russian' => 'с.'),
            'pp.'   => array('russian' => 'с.'),
            'P.'    => array('russian' => 'С.'),
            'Pp.'   => array('russian' => 'С.'),
            'Vol.'  => array('russian' => 'Т.'),
            'no.'   => array('russian' => '№'),
            'et&nbsp;al.'  => array('russian' =>  'и&nbsp;др.'),
            'Ed.&nbsp;by'  => array('russian' =>  'Под&nbsp;ред.'),
            'leader'  => array('russian' =>  'рук.'),
        );

    protected function _($str)
    {
        if (empty($this->entry['language'])) {return $str;};
        if (empty($this->I18N[$str]))        {return $str;};
        if (empty($this->I18N[$str][$this->entry['language']]))    {return $str;};

        return $this->I18N[$str][$this->entry['language']];
    }


    /*
     * Compare entries for sorting
     *
     */
    protected function cmp_entries(&$a, &$b)
    {
        $x = preg_match('/.*([0-9]{4})/u', $a['year'], $matches) ? $matches[1] : 0;
        $y = preg_match('/.*([0-9]{4})/u', $b['year'], $matches) ? $matches[1] : 0;
        if ($x > $y) {return -1;};
        if ($x < $y) {return  1;};

        // by entry type
        $type = array (
            'article' => 10,
            'book'    => 20,
            'inbook'  => 30,
            'booklet' => 40,
            'inproceedings' => 50,
            'grant'    => 1000, // for grants, not for publications ;-)
            'misc'     => 999999, // We use misc for articles in non-reviewed journals
        );
        $x = $type[$a['entry']]; // FIXME : other entry type if needed
        $y = $type[$b['entry']];
        if ($x < $y) {return -1;};
        if ($x > $y) {return  1;};


        // by journal importance,
        // which is defined by order of @STRING commands for BiBTeX
        // (strings are stored in $this->STRINGS)
        $x = empty($a['journal']) ? 'NONE' : $a['journal'];
        $y = empty($b['journal']) ? 'NONE' : $b['journal'];
        // Not a journal. Maybe grant?
        if (($x === 'NONE') && ($y === 'NONE'))
        {   // 'organization' is my (Igor's) extention
            $x = empty($a['organization']) ? 'NONE' : $a['organization'];
            $y = empty($b['organization']) ? 'NONE' : $b['organization'];
        }
        $x = empty($this->STRINGS_o[$x]) ? 999999 : $this->STRINGS_o[$x];
        $y = empty($this->STRINGS_o[$y]) ? 999999 : $this->STRINGS_o[$y];
        if ($x < $y) {return -1;};
        if ($x > $y) {return  1;};

        return 0;
    }

    public function sort()
    {
        usort($this->SELECTION, array($this, 'cmp_entries'));
    }

    protected function format_field_default($field)
    {
        $this->entry[$field] = $this->latex2html(
            $this->expand_string($this->entry[$field])
            );
    }

    protected function format_organization() // for @GRANT
    {
        $this->format_field_default('organization');
        $this->entry['organization'] = '<strong>' . $this->entry['organization'] . '</strong>';
    }

    protected function format_doer() // for @GRANT
    {
        $res = '';
        $authors_array = preg_split('/\s+and\s+/',
            $this->expand_string($this->entry['doer']));
       
        foreach ($authors_array as &$a)
        {
            $a = $this->format_author1($a);
        }

        if(isset($authors_array[1]))
            $authors_array[0] .= '&nbsp;(' . $this->_('leader') . ')';
        $res = implode(', ', $authors_array);

        $this->entry['doer'] = $res;
    }

    protected function format_pages()
    {
        $this->format_field_default('pages');
        $pp = preg_match('/\d+\D+\d+/', $this->entry['pages']) ?  $this->_('Pp.') : $this->_('P.');
        $this->entry['pages'] = $pp . '&nbsp;' . $this->entry['pages'];
    }

    protected function format_numpages()
    {
        $this->format_field_default('numpages');
        $this->entry['numpages'] = $this->entry['numpages'] . '&nbsp;'
            . $this->_(($this->entry['numpages'] > 1) ? 'pp.' : 'p.');
    }

    protected function format_editor()
    {
        $this->format_field_default('editor');
        $this->entry['editor'] = $this->_('Ed.&nbsp;by') . '&nbsp;' . $this->entry['editor'];
    }

    protected function format_volume()
    {
        $this->format_field_default('volume');
        $this->entry['volume'] = $this->_('Vol.') . '&nbsp;' . $this->entry['volume'];
    }

    protected function format_url()
    {
        $this->format_field_default('url');
        $this->entry['url'] = ' <a href="' . $this->entry['url']
                . '" >' . htmlentities(urldecode($this->entry['url'])) . '</a>';
    }

    protected function format_number()
    {
        $this->format_field_default('number');
        $this->entry['number'] = $this->_('no.') . '&nbsp;' . $this->entry['number'];
    }

    protected function format_author1($author)
    {
        $res = '';
        $res = $this->latex2html($author);
        return $res;
    }

    protected function format_author()
    {
        $res = '';
        $authors_array = preg_split('/\s+and\s+/',
            $this->expand_string($this->entry['author']));
       
        $this->entry['count_authors'] = count($authors_array);
        array_splice($authors_array, 3);
        
        foreach ($authors_array as &$a)
        {
            $a = $this->format_author1($a);
        }

        $res = implode(', ', $authors_array);
        if ($this->entry['count_authors'] > 3)
        {
            $res .= ' ' . $this->_('et&nbsp;al.');
        }

        $this->entry['author'] = $res;
    }
 
    /*
     * Format one BiBTeX entry in HTML
     *
     */
    public function format(&$entry)
    {
        $res = '';

        // test
        // $entry = $this->SELECTION[0];
        // test

        $this->entry = $entry;

        foreach ($this->entry as $field => $value)
        {
            $method = "format_$field";
            if (method_exists($this, $method))
            {
                $this->$method(); // prepare a field for final HTML output
            }
            else
            {
                $this->format_field_default($field);
            }
        }

        $method = 'format_' . $this->entry['entry'];
        if (method_exists($this, $method))
        {
            $res = $this->$method();
        }
        else
        {
            $res = 'Not implemented for "' . $this->entry['entry'] . '"';
        }

        $res .= '.';
        if (!empty($this->entry['url']))
        {
            $res .= $this->entry['url'];
        }
        $res = preg_replace('/\.(<[^>]+>)*?\.+/', '.\1', $res);
        return $res;
    }

    protected function format_book()
    {
        $parts = array(); // All parts are connected with '.&nbsp;&mdash; '

        $part = '';
        // FIXME : If $this->entry['count_authors'] > 3, place them after title?
        if (!empty($this->entry['author']))
        {
            $part = '<em>' . $this->entry['author'] . '</em>';
        }
        if (!empty($this->entry['title']))
        {
            $part .= (empty($part) ? '' :  '. ') . $this->entry['title'];
        }
        if (!empty($this->entry['editor']))
        {
            $part .= '&nbsp;/ ' . $this->entry['editor'];
        }
        $parts[] = $part;

        if (!empty($this->entry['edition']))
        {
            $parts[] = $this->entry['edition'];
        }

        if (!empty($this->entry['volume']))
        {
            $parts[] = $this->entry['volume'];
        }

        $part = '';
        if (!empty($this->entry['address']))
        {
            $part .= $this->entry['address'];
        }
        if (!empty($this->entry['publisher']))
        {
            $part .= (empty($part) ? '' : ': ') . $this->entry['publisher'];
        }
        if (!empty($this->entry['year'])) // We are ignoring month
        {
            $part .= (empty($part) ? '' : ', ') . $this->entry['year'];
        }
        $parts[] = $part;

        if (!empty($this->entry['numpages']))
        {
            $parts[] = $this->entry['numpages'];
        }
        elseif (!empty($this->entry['pages']))
        {
            $parts[] = $this->entry['pages'];
        }



        return implode('.&nbsp;&mdash; ', $parts);
    }

    protected function format_article()
    {
        $parts = array(); // All parts are connected with '.&nbsp;&mdash; '

        $part = '';
        // FIXME : If $this->entry['count_authors'] > 3, place them after title?
        if (!empty($this->entry['author']))
        {
            $part = '<em>' . $this->entry['author'] . '</em>';
        }
        if (!empty($this->entry['title']))
        {
            $part .= (empty($part) ? '' :  '. ') . $this->entry['title'];
        }
        if (!empty($this->entry['journal']))
        {
            $part .= '&nbsp;// <em>' . $this->entry['journal'] . '</em>';
        }
        $parts[] = $part;

        if (!empty($this->entry['year']))
        {
            $parts[] = $this->entry['year'];
        }

        if (!empty($this->entry['month']))
        {
            $parts[] = $this->entry['month'];
        }

        $part = '';
        if (!empty($this->entry['volume']))
        {
            $part .= $this->entry['volume'];
        }
        if (!empty($this->entry['number']))
        {
            $part .= (empty($part) ? '' :  ', ') . $this->entry['number'];
        }
        $parts[] = $part;
       
        if (!empty($this->entry['pages']))
        {
            $parts[] = $this->entry['pages'];
        }

        return implode('.&nbsp;&mdash; ', $parts);
    }

    protected function format_grant()
    {
        $parts = array(); // All parts are connected with '.&nbsp;&mdash; '


        if (!empty($this->entry['organization']))
        {
            $parts[] = $this->entry['organization'];
        }

        if (!empty($this->entry['number']))
        {
            $parts[] = $this->entry['number'];
        }

        if (!empty($this->entry['title']))
        {
            $parts[] = $this->entry['title'];
        }

        if (!empty($this->entry['years']))
        {
            $parts[] = $this->entry['years'];
        }
        elseif (!empty($this->entry['year']))
        {
            $parts[] = $this->entry['year'];
        }


        if (!empty($this->entry['doer']))
        {
            $parts[] = '<em>' . $this->entry['doer'] . '</em>';
        }

        return implode('.&nbsp;&mdash; ', $parts);
    }


    protected function format_inproceedings()
    {
        $parts = array(); // All parts are connected with '.&nbsp;&mdash; '

        $part = '';
        if (!empty($this->entry['author']))
        {
            $part = '<em>' . $this->entry['author'] . '</em>';
        }
        if (!empty($this->entry['title']))
        {
            $part .= (empty($part) ? '' :  '. ') . $this->entry['title'];
        }
        if (!empty($this->entry['booktitle']))
        {
            $part .= '&nbsp;// ' . $this->entry['booktitle'];
        }
        $parts[] = $part;

        if (!empty($this->entry['volume']))
        {
            $parts[] = $this->entry['volume'];
        }

        $part = '';
        if (!empty($this->entry['address']))
        {
            $part .= $this->entry['address'];
        }
        if (!empty($this->entry['year'])) // We are ignoring month
        {
            $part .= (empty($part) ? '' : ', ') . $this->entry['year'];
        }
        $parts[] = $part;

        if (!empty($this->entry['pages']))
        {
            $parts[] = $this->entry['pages'];
        }

        return implode('.&nbsp;&mdash; ', $parts);
    }


    protected function format_booklet()
    {
         return $this->format_book();
    }


    protected function format_misc()
    {
         return $this->format_article(); // Use @misc for articles in non-reviewed journals
    }
}


class BibtexParserWorker extends BibtexParserTeam
{
    protected function cmp_entries(&$a, &$b)
    {
        // by entry type
        $type = array (
            'article' => 10,
            'book'    => 20,
            'inbook'  => 30,
            'booklet' => 40,
            'inproceedings' => 50,
            'grant'    => 1000, // for grants, not for publications ;-)
            'misc'     => 999999, // We use misc for articles in non-reviewed journals
        );
        $x = $type[$a['entry']]; // FIXME : other entry type if needed
        $y = $type[$b['entry']];
        if ($x < $y) {return -1;};
        if ($x > $y) {return  1;};

        // by year (if range - by last year)
        $x = preg_match('/.*(\d{4})/u', $a['year'], $matches) ? $matches[1] : 0;
        $y = preg_match('/.*(\d{4})/u', $b['year'], $matches) ? $matches[1] : 0;
        // die ("$x < $y");
        if ($x > $y) {return -1;};
        if ($x < $y) {return  1;};


        // by journal importance,
        // which is defined by order of @STRING commands for BiBTeX
        // (strings are stored in $this->STRINGS)
        $x = empty($a['journal']) ? 'NONE' : $a['journal'];
        $y = empty($b['journal']) ? 'NONE' : $b['journal'];
        // Not a journal. Maybe grant?
        if (($x === 'NONE') && ($y === 'NONE'))
        {   // 'organization' is my (Igor's) extention
            $x = empty($a['organization']) ? 'NONE' : $a['organization'];
            $y = empty($b['organization']) ? 'NONE' : $b['organization'];
        }
        $x = empty($this->STRINGS_o[$x]) ? 999999 : $this->STRINGS_o[$x];
        $y = empty($this->STRINGS_o[$y]) ? 999999 : $this->STRINGS_o[$y];
        if ($x < $y) {return -1;};
        if ($x > $y) {return  1;};

        return 0;
    }
}

?>

