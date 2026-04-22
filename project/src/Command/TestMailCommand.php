<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(name: 'app:test-mail')]
final class TestMailCommand extends Command
{
    public function __construct(private readonly MailerInterface $mailer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (new Email())
            ->from('dahmenmalek8@gmail.com')
            ->to('bendahmen23@gmail.com')
            ->subject('SMTP Test Serinity')
            ->text('This is a Gmail SMTP test from Serinity.');

        try {
            $this->mailer->send($email);
            $output->writeln('MAIL SENT SUCCESSFULLY');
        } catch (\Throwable $e) {
            $output->writeln('MAIL ERROR: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
