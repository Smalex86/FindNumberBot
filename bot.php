<?php

header('Content-Type: text/html; charset=utf-8');
require __DIR__ . '/vendor/autoload.php';

// дебаг
if(true){
	error_reporting(E_ALL & ~(E_NOTICE | E_USER_NOTICE | E_DEPRECATED));
	ini_set('display_errors', 1);
}

// создаем переменную бота
$token = "";
include "secret.php";
$bot = new \TelegramBot\Api\Client($token,null);

// если бот еще не зарегистрирован - регистируем
if (!file_exists("registered.trigger")) { 
	/**
	 * файл registered.trigger будет создаваться после регистрации бота. 
	 * если этого файла нет значит бот не зарегистрирован 
	 */
	// URl текущей страницы
	$page_url = "https://".$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	$result = $bot->setWebhook($page_url);
	if($result){
		file_put_contents("registered.trigger",time()); // создаем файл дабы прекратить повторные регистрации
	} else die("ошибка регистрации");
}

// Команды бота
// пинг
$bot->command('ping', function ($message) use ($bot) {
	$bot->sendMessage($message->getChat()->getId(), 'pong!');
});

$bot->command('author', function ($message) use ($bot) {
	$bot->sendMessage($message->getChat()->getId(), 'Smirnov Alexander');
});

// старт новой игры
$bot->command('newGame', function ($message) use ($bot) {
	// определить имя текущего пользователя
	$uid = $message->getChat()->getId();
	$fileName = __DIR__ . '/games/' . $uid . '.game'; // имя файла для записи загаданного числа
	// получить случайное число
	$number = (string) rand(1000, 9999);
	// записать значение в файл
	if (file_put_contents($fileName, $number)) {
		$bot->sendMessage($message->getChat()->getId(), 'Компьютер загадал число! :)');
		setLogMsg(0, __FILE__ . ' : ' . __LINE__ . ' -- newGame filename = ' . $fileName . ' number = ' . $number);	
	} else	
		$bot->sendMessage($message->getChat()->getId(), 'Ошибка :(');
});

// обязательное. Запуск бота
$bot->command('start', function ($message) use ($bot) {
    $answer = 'Добро пожаловать! 
Чтобы начать игру введите команду /newGame
Компьютер загадывает 4-х значное число (цифры могут повторяться). Ваша задача угадать это число. Если цифра присутствует в загаданном числе, и она стоит на том месте, то компьютер обозначает такую цифру буквой "В". Если цифра присутствует, но она стоит не на том месте, то компьютер обозначает ее буквой "K". 
Ваша задача угадать загаданное число за наименьшее число ходов.';
    $bot->sendMessage($message->getChat()->getId(), $answer);
});

// помощь
$bot->command('help', function ($message) use ($bot) {
    $answer = 'Команды:
/help - помощь
/start - запуск бота и правила игры
/newGame - новая игра';
    $bot->sendMessage($message->getChat()->getId(), $answer);
});

// Отлов сообщений
$bot->on(function($Update) use ($bot){
	
	$message = $Update->getMessage();
	$mtext = $message->getText();
	$uid = $message->getChat()->getId();
	
	$fileName = __DIR__ . '/games/' . $uid . '.game'; // имя файла с загаданным числом
	$fileCount = __DIR__ . '/games/' . $uid . '.count'; // имя файла с количеством попыток
	
	if ($number = file_get_contents($fileName)) {	
		// прямое сравнение
		if ($number == $mtext) {
			$count = file_get_contents($fileCount);
			$bot->sendMessage($message->getChat()->getId(), sprintf('Поздравляем, Вы угадали! %sКомпьютер загадывал число: %s. ', 
				($count) ? 'Попыток было сделано: ' . $count . '. ': '', $number));
			unlink($fileName); // удалить файл с загаданным числом
			if ($count) unlink($fileCount); // удалить файл с количеством попыток, если он есть
		} else {
			$res = '****';
			// выполнить сравнение по символам
			for ($i = 0; ($i <= strlen($number)-1); $i++) {
				$ch = $number[$i];
				$offset = 0;
				while (($offset = strpos($mtext, $ch, $offset)) !== false) {
					if ($offset == $i) 
						// символ на своем месте
						$res[$offset] = 'B';
					else {
						// проверить, не был ли найден уже этот символ 
						if ($res[$offset] != 'B')
							// символ не на своем месте
							$res[$offset] = 'K';
					}
					$offset++; 
				}
			}
			$bot->sendMessage($message->getChat()->getId(), $res);
			// получить текущее количество попыток, увеличить и записать в файл обратно
			if ($number = file_get_contents($fileCount)) {
				$number++;
			} else	
				// если файла нет, то значит это первая попытка
				$number = 1;
			file_put_contents($fileCount, $number);
		}
	} else {
		$bot->sendMessage($message->getChat()->getId(), 'Чтобы начать игру воспользуйтесь командой /newGame.');
	}
	
}, function($message) use ($name){
	return true; // когда тут true - команда проходит
});

// запускаем обработку
$bot->run();

echo "бот";