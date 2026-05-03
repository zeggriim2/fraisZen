<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
abstract class AbstractAdminController extends AbstractController
{
}
