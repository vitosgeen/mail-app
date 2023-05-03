<?php

namespace App\Command;

use App\Entity\UserClients;
use App\Form\Handler\MailingFormHandler;
use App\Repository\MailingItemRepository;
use App\Repository\MailingRepository;
use App\Repository\UserClientsRepository;
use App\Utils\Manager\MailingManager;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MailingSendNewMailsAwsCommand extends Command
{
    const TYPE_ARG = "test";
    const SLEEP = 1;
    const TYPE_MARKER_MAIL = "new-mails-aws";
    protected static $defaultName = 'app:mailing:send:new-mails-aws';
    protected static $defaultDescription = 'Mailing new mails for men';

    private $customConnect;
    private $mailing;
    private $mailingItem;
    private $userClientsRepository;
    private $mailingFormHandler;
    private $mailingManager;
    public function __construct(ManagerRegistry $doctrine, 
                                MailingRepository $mailing, 
                                MailingItemRepository $mailingItem, 
                                UserClientsRepository $userClientsRepository,
                                MailingFormHandler $mailingFormHandler,
                                MailingManager $mailingManager) 
    {
        $this->customConnect = $doctrine->getConnection('customer');
        $this->mailing = $mailing;
        $this->mailingItem = $mailingItem;
        $this->userClientsRepository = $userClientsRepository;
        $this->mailingFormHandler = $mailingFormHandler;
        $this->mailingManager = $mailingManager;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $arg1 = $input->getArgument('arg1');

        $isTest = false;
        if ($arg1 && $arg1 == self::TYPE_ARG) {
            $io->note(sprintf('You passed an argument: %s', $arg1));
            $isTest = true;
        }

        $m = $this->mailingManager->getMailingNewMailsCronTask();
        if (!$m) {
            $io->success("Cron PROMO doesn't have a task");
            return Command::SUCCESS;
        }
        $us = new UserClients();
        
        if ($isTest) {
            $io->note("It is test");
            $uss = $us->getUsersForMailingNewMails($this->customConnect, $this->userClientsRepository, [200000, 274755, 102449, 376650, 117355]);
        } else {
            $uss = $us->getUsersForMailingNewMails($this->customConnect, $this->userClientsRepository);
        }
        if (empty($uss)) {
            $io->warning('Mailing does not have users for new mails');
        }
        
        if ($input->getOption('option1')) {
            // ...
        }
        $emails = array();
        $marker = $this->mailingFormHandler->createMarkerEmailUTM(self::TYPE_MARKER_MAIL);
        // $this->mailingManager->saveMailingStatus($m, MailingManager::MAILING_STATUS_BUSY);
        $urlStrRoot = $_ENV["APP_UPDATE_INBOX_URL"];
        foreach ($uss as $key => $man) {
            $email = strtolower($man['user_email']);
            if (!empty($emails[$email])) {
              continue;
            }
            $emails[$email] = $email;
            $uid = $man['user_id'];
            $email = $man['user_email'];
            $fn = $man['user_name'];
            $ln = $man['user_surname'];
            $visible = $man['user_visible'];
            $urlStr = "";
            $isActive = $us->userIsActive($this->customConnect, $this->userClientsRepository, $uid);
            if (!$isActive) {
                continue;
            }
            if (empty($visible)) {
              $urlStr = $urlStrRoot . $uid;
              file_get_contents($urlStr);
            }
            $isUnsubscribeNewMails = $us->deliveryCheckUnsubscribeNewMails($this->customConnect, $this->userClientsRepository, $man["user_id"]);
            if ($isUnsubscribeNewMails) {
                $this->mailingFormHandler->userUnsubscribeMailing($m, $man);
                continue;
            }
            $ladiesMail = $us->getNewMailsLadies($this->customConnect, $this->userClientsRepository, $uid, $fn, $ln);
            dd($ladiesMail);
            $io->note(sprintf('User %s: %s , visible: %s', $uid, $email, $visible));

            sleep(self::SLEEP);
        }

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');
        // $this->mailingManager->saveMailingStatus($m, MailingManager::MAILING_STATUS_FINISHED);
        // $ma = $this->mailingManager->getMailingNewMailsCronTask();
        // if (!$ma) {
        //     $this->mailingManager->createMailingCloneStatus($m, MailingManager::MAILING_STATUS_ACTIVE);
        // }
        
        return Command::SUCCESS;
    }
}