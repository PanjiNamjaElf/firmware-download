<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Console command to create or reset an admin user account.
 *
 * This is the only way to create admin accounts — there is no self-registration
 * on the admin panel. Run this command once during initial setup to create
 * the first admin user, or any time you need to reset a password.
 *
 * Usage:
 *   php bin/console app:create-admin-user
 *   php bin/console app:create-admin-user --username=admin --password=secret1234
 *   php bin/console app:create-admin-user --reset  (resets password of existing user)
 */
#[AsCommand(
    name: 'app:create-admin-user',
    description: 'Creates or resets an admin user account for the admin panel.',
)]
class CreateAdminUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    /**
     * Defines the command options.
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'username',
                shortcut: 'u',
                mode: InputOption::VALUE_OPTIONAL,
                description: 'Username for the admin account.',
            )
            ->addOption(
                name: 'password',
                shortcut: 'p',
                mode: InputOption::VALUE_OPTIONAL,
                description: 'Password for the admin account.',
            )
            ->addOption(
                name: 'reset',
                mode: InputOption::VALUE_NONE,
                description: 'Reset the password of an existing admin user.',
            );
    }

    /**
     * Executes the command interactively, prompting for username and password
     * if not provided as options.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input  The command input.
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output  The command output.
     * @return int Command exit code.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Admin User Setup');

        // Resolve username — from option or prompt.
        $username = $input->getOption('username');

        if (empty($username)) {
            $username = $io->ask('Admin username', 'admin');
        }

        if (empty($username)) {
            $io->error('Username cannot be empty.');

            return Command::FAILURE;
        }

        // Resolve password — from option or prompt (hidden input).
        $password = $input->getOption('password');

        if (empty($password)) {
            $password = $io->askHidden('Admin password (input is hidden)');
            $confirm = $io->askHidden('Confirm password');

            if ($password !== $confirm) {
                $io->error('Passwords do not match.');

                return Command::FAILURE;
            }
        }

        if (empty($password) || strlen($password) < 8) {
            $io->error('Password must be at least 8 characters long.');

            return Command::FAILURE;
        }

        // Find existing user or create a new one.
        $repository = $this->entityManager->getRepository(AdminUser::class);
        $user = $repository->findOneBy(['username' => $username]);

        $isNew = false;

        if ($user === null) {
            if ($input->getOption('reset')) {
                $io->error(sprintf('No admin user with username "%s" was found. Cannot reset.', $username));

                return Command::FAILURE;
            }

            $user = new AdminUser();
            $isNew = true;

            $io->text(sprintf('Creating new admin user: <info>%s</info>', $username));
        } else {
            $io->text(sprintf('Updating existing admin user: <info>%s</info>', $username));
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);

        $user->setUsername($username);
        $user->setPassword($hashedPassword);
        $user->setRoles(['ROLE_ADMIN']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        if ($isNew) {
            $io->success(sprintf('Admin user "%s" created successfully.', $username));
        } else {
            $io->success(sprintf('Password for admin user "%s" updated successfully.', $username));
        }

        $io->note('You can now log in at /admin/login');

        return Command::SUCCESS;
    }
}
