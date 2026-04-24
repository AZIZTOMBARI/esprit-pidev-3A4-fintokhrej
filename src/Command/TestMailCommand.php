<?php
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(name: 'test:mail', description: 'Send a test email')]
class TestMailCommand extends Command
{
    public function __construct(private MailerInterface $mailer) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $to = $input->getArgument('to');
        $email = (new Email())
            ->from('touzihayfa9@gmail.com')
            ->to($to)
            ->subject('Test FinTokhrej')
            ->text('Test mail');
        try {
            $this->mailer->send($email);
            $output->writeln('OK Mail envoye');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('ERREUR : '.$e->getMessage());
            $output->writeln('Classe : '.get_class($e));
            if ($e->getPrevious()) {
                $output->writeln('Previous : '.$e->getPrevious()->getMessage());
            }
            return Command::FAILURE;
        }
    }
}