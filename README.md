# GPT-3-Encoder-PHP
PHP BPE Text Encoder for GPT-2 / GPT-3

## About
GPT-2 and GPT-3 use byte pair encoding to turn text into a series of integers to feed into the model. This is a javascript implementation of OpenAI's original python encoder/decoder which can be found [here](https://github.com/openai/gpt-2)

This specific encoder is used in one of my [WordPress plugins](https://coderevolution.ro), to count the number of tokens a string will use when sent to OpenAI API.


## Usage

The mbstring PHP extension is needed for this tool to work correctly (in case non-ASCII characters are present in the tokenized text: [details here on how to install mbstring](https://www.php.net/manual/en/mbstring.installation.php)


```php

$prompt = "Many words map to one token, but some don't: indivisible. Unicode characters like emojis may be split into many tokens containing the underlying bytes: 🤚🏾 Sequences of characters commonly found next to each other may be grouped together: 1234567890";

$token_array = gpt_encode($prompt);

```



