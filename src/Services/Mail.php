<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mail
{
    public function sendMail(string $email, string $name, string $message)
    {
        $mail = new PHPMailer(true);

        try {
            $password = trim(file_get_contents(__DIR__ . '/../../var/password.txt'));
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'dmitrybeznosiukcode@gmail.com';
            /////////////password in /var/password.txt
            $mail->Password = $password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;


            $mail->setFrom('dmitrybeznosiukcode@gmail.com', $name);
            $mail->addAddress('dimapryme@gmail.com');

            // Тема та текст листа
            $mail->isHTML(true);
            $mail->Subject = 'Request from Logistic site';
            $mail->Body = "<h1>$name</h1>
                           <h6>$email</h6><br>
                           <p>$message</p>";
            $mail->AltBody = "$name\n$email\n\n$message";

            // Відправка
            $mail->send();
            echo 'Лист успішно надіслано';
        }
        catch (Exception $e) {
            echo "Помилка: Лист не вдалося надіслати. Причина: $mail->ErrorInfo $e";
        }
    }
}
