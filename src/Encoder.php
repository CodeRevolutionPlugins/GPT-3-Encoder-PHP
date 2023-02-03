<?php

namespace CodeRevolutionPlugins\GPT3Encoder;

class Encoder
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = self::create();
        }

        return self::$instance;
    }

    private array $encoder;
    private array $byte_encoder;
    private array $bpe_ranks;
    public function __construct($encoder, $byte_encoder, $bpe_ranks)
    {
        $this->encoder = $encoder;
        $this->byte_encoder = $byte_encoder;
        $this->bpe_ranks = $bpe_ranks;
    }

    public static function create(): self
    {
        $raw_chars = file_get_contents(dirname(__FILE__) . "/../data/characters.json");
        $byte_encoder = json_decode($raw_chars, true);
        if(empty($byte_encoder)) {
            throw new \Exception('Failed to load characters.json');
        }

        $rencoder = file_get_contents(dirname(__FILE__) . "/../data/encoder.json");
        $encoder = json_decode($rencoder, true);
        if(empty($encoder)) {
            throw new \Exception('Failed to load encoder.json');
        }

        $bpe_file = file_get_contents(dirname(__FILE__) . "/../data/vocab.bpe");
        if(empty($bpe_file)) {
            throw new \Exception('Failed to load vocab.bpe');
        }
        $lines = preg_split('/\r\n|\r|\n/', $bpe_file);
        $bpe_merges = array();
        $bpe_merges_temp = array_slice($lines, 1, count($lines), true);
        foreach($bpe_merges_temp as $bmt)
        {
            $split_bmt = preg_split('#(\s+)#', $bmt);
            $split_bmt = array_filter($split_bmt, function ($var) {
                return ($var !== NULL && $var !== FALSE && $var !== '');
            });

            if(count($split_bmt) > 0)
            {
                $bpe_merges[] = $split_bmt;
            }
        }
        $bpe_ranks = self::dictZip($bpe_merges, range(0, count($bpe_merges) - 1));

        return new self(
            $encoder,
            $byte_encoder,
            $bpe_ranks
        );
    }

    public function encode(string $text): array
    {
        $bpe_tokens = array();
        if(empty($text))
        {
            return $bpe_tokens;
        }

        preg_match_all("#'s|'t|'re|'ve|'m|'ll|'d| ?\p{L}+| ?\p{N}+| ?[^\s\p{L}\p{N}]+|\s+(?!\S)|\s+#u", $text, $matches);
        if(!isset($matches[0]) || count($matches[0]) == 0)
        {
            error_log('Failed to match string: ' . $text);
            return $bpe_tokens;
        }

        $cache = array();
        foreach($matches[0] as $token)
        {
            $new_tokens = array();
            $chars = array();
            $token = utf8_encode($token);
            if(function_exists('mb_strlen'))
            {
                $len = mb_strlen($token, 'UTF-8');
                for ($i = 0; $i < $len; $i++)
                {
                    $chars[] = mb_substr($token, $i, 1, 'UTF-8');
                }
            }
            else
            {
                $chars = str_split($token);
            }
            $result_word = '';
            foreach($chars as $char)
            {
                if(isset($this->byte_encoder[$this->unichr($char)]))
                {
                    $result_word .= $this->byte_encoder[$this->unichr($char)];
                }
            }
            $new_tokens_bpe = $this->bpe($result_word, $cache);
            $new_tokens_bpe = explode(' ', $new_tokens_bpe);
            foreach($new_tokens_bpe as $x)
            {
                if(isset($encoder[$x]))
                {
                    $new_tokens[$x] = $encoder[$x];
                }
                else
                {
                    $new_tokens[$x] = $x;
                }
            }
            foreach($new_tokens as $ninx => $nval)
            {
                if(isset($bpe_tokens[$ninx]))
                {
                    $bpe_tokens[] = $nval;
                }
                else
                {
                    $bpe_tokens[$ninx] = $nval;
                }
            }
        }
        return $bpe_tokens;
    }

    private function unichr($c)
    {
        if (ord($c[0]) >=0 && ord($c[0]) <= 127)
        {
            return ord($c[0]);
        }
        if (ord($c[0]) >= 192 && ord($c[0]) <= 223)
        {
            return (ord($c[0])-192)*64 + (ord($c[1])-128);
        }
        if (ord($c[0]) >= 224 && ord($c[0]) <= 239)
        {
            return (ord($c[0])-224)*4096 + (ord($c[1])-128)*64 + (ord($c[2])-128);
        }
        if (ord($c[0]) >= 240 && ord($c[0]) <= 247)
        {
            return (ord($c[0])-240)*262144 + (ord($c[1])-128)*4096 + (ord($c[2])-128)*64 + (ord($c[3])-128);
        }
        if (ord($c[0]) >= 248 && ord($c[0]) <= 251)
        {
            return (ord($c[0])-248)*16777216 + (ord($c[1])-128)*262144 + (ord($c[2])-128)*4096 + (ord($c[3])-128)*64 + (ord($c[4])-128);
        }
        if (ord($c[0]) >= 252 && ord($c[0]) <= 253)
        {
            return (ord($c[0])-252)*1073741824 + (ord($c[1])-128)*16777216 + (ord($c[2])-128)*262144 + (ord($c[3])-128)*4096 + (ord($c[4])-128)*64 + (ord($c[5])-128);
        }
        if (ord($c[0]) >= 254 && ord($c[0]) <= 255)
        {
            return 0;
        }
        return 0;
    }

    private static function dictZip($x, $y)
    {
        $result = array();
        $cnt = 0;
        foreach($x as $i)
        {
            if(isset($i[1]) && isset($i[0]))
            {
                $result[$i[0] . ',' . $i[1]] = $cnt;
                $cnt++;
            }
        }
        return $result;
    }

    private function get_pairs($word)
    {
        $pairs = array();
        $prev_char = $word[0];
        for ($i = 1; $i < count($word); $i++)
        {
            $char = $word[$i];
            $pairs[] = array($prev_char, $char);
            $prev_char = $char;
        }
        return $pairs;
    }

    private function split($str, $len = 1)
    {
        $arr		= [];
        if(function_exists('mb_strlen'))
        {
            $length 	= mb_strlen($str, 'UTF-8');
        }
        else
        {
            $length 	= strlen($str);
        }

        for ($i = 0; $i < $length; $i += $len)
        {
            if(function_exists('mb_substr'))
            {
                $arr[] = mb_substr($str, $i, $len, 'UTF-8');
            }
            else
            {
                $arr[] = substr($str, $i, $len);
            }
        }
        return $arr;

    }

    private function bpe($token, &$cache)
    {
        if(array_key_exists($token, $cache))
        {
            return $cache[$token];
        }
        $word = $this->split($token);
        $init_len = count($word);
        $pairs = $this->get_pairs($word);
        if(!$pairs)
        {
            return $token;
        }
        while (true)
        {
            $minPairs = array();
            foreach($pairs as $pair)
            {
                if(array_key_exists($pair[0] . ','. $pair[1], $this->bpe_ranks))
                {
                    $rank = $this->bpe_ranks[$pair[0] . ','. $pair[1]];
                    $minPairs[$rank] = $pair;
                }
                else
                {
                    $minPairs[10e10] = $pair;
                }
            }
            ksort($minPairs);
            $min_key = array_key_first($minPairs);
            foreach($minPairs as $mpi => $mp)
            {
                if($mpi < $min_key)
                {
                    $min_key = $mpi;
                }
            }
            $bigram = $minPairs[$min_key];
            if(!array_key_exists($bigram[0] . ',' . $bigram[1], $this->bpe_ranks))
            {
                break;
            }
            $first = $bigram[0];
            $second = $bigram[1];
            $new_word = array();
            $i = 0;
            while ($i < count($word))
            {
                $j = $this->indexOf($word, $first, $i);
                if ($j === -1)
                {
                    $new_word = array_merge($new_word, array_slice($word, $i, null, true));
                    break;
                }
                if($i > $j)
                {
                    $slicer = array();
                }
                elseif($j == 0)
                {
                    $slicer = array();
                }
                else
                {
                    $slicer = array_slice($word, $i, $j - $i, true);
                }
                $new_word = array_merge($new_word, $slicer);
                if(count($new_word) > $init_len)
                {
                    break;
                }
                $i = $j;
                if ($word[$i] === $first && $i < count($word) - 1 && $word[$i + 1] === $second)
                {
                    array_push($new_word, $first . $second);
                    $i = $i + 2;
                }
                else
                {
                    array_push($new_word, $word[$i]);
                    $i = $i + 1;
                }
            }
            if($word == $new_word)
            {
                break;
            }
            $word = $new_word;
            if (count($word) === 1)
            {
                break;
            }
            else
            {
                $pairs = $this->get_pairs($word);
            }
        }
        $word = implode(' ', $word);
        $cache[$token] = $word;
        return $word;
    }

    private function indexOf($arrax, $searchElement, $fromIndex)
    {
        $index = 0;
        foreach($arrax as $index => $value)
        {
            if($index < $fromIndex)
            {
                $index++;
                continue;
            }
            if($value == $searchElement)
            {
                return $index;
            }
            $index++;
        }
        return -1;
    }
}