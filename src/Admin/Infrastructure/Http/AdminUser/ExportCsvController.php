<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminUser;

use App\Admin\Infrastructure\Http\AbstractAdminController;
use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Webmozart\Assert\Assert;

#[Route('/users/export', name: 'exportCsv', methods: [Request::METHOD_GET])]
class ExportCsvController extends AbstractAdminController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PersonRepositoryInterface $personRepository,
    ) {
    }

    public function __invoke(): StreamedResponse
    {
        $users = $this->userRepository->findAll();

        $response = new StreamedResponse(function () use ($users) {
            $handle = fopen('php://output', 'w');
            Assert::resource($handle);
            fputcsv($handle, ['ID', 'Email', 'Statut', 'Inscription', 'Nb personnes']);

            foreach ($users as $user) {
                $persons = $this->personRepository->findAllByUserId($user->id()->value());
                fputcsv($handle, [
                    $user->id()->value(),
                    $user->email(),
                    $user->subscriptionStatus() ?? '',
                    $user->createdAt()->format('Y-m-d H:i:s'),
                    count($persons),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="users-'.date('Y-m-d').'.csv"');

        return $response;
    }
}
