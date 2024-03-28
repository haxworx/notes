<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\NoteType;
use App\Repository\NoteRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Routing\Attribute\Route;

class PageController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly NotifierInterface $notifier,
    ) {
    }

    #[Route('/', name: 'app_index')]
    public function index(
        NoteRepository $repo,
    ): Response {
        $user = $this->getUser();

        $notes = $repo->findAllByUserId($user->getId());

        return $this->render('page/index.html.twig', [
            'notes' => $notes,
        ]);
    }

    #[Route('/create', name: 'app_create')]
    public function create(
        Request $request,
        NoteRepository $repo,
    ): Response {
        $user = $this->getUser();
        $form = $this->createForm(NoteType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $note = $form->getData();
            $note->setUserId($user->getId());
            $note->setTimestamp(new \DateTimeImmutable('NOW'));
            $repo->save($note);
            $this->logger->info('Note created', [
                'note' => $note->getId(),
            ]);
            $this->notifier->send(new Notification('Note created.', ['browser']));

            return $this->redirectToRoute('app_edit', [
                'id' => $note->getId(),
            ]);
        }

        return $this->render('page/create.html.twig', [
            'form' => $form,
            'is_edit' => false,
        ]);
    }

    #[Route('/edit/{id}', name: 'app_edit')]
    public function edit(
        Request $request,
        int $id,
        NoteRepository $repo,
    ): Response {
        $user = $this->getUser();
        $note = $repo->findOneByIdAndUserId($id, $user->getId());
        if (!$note) {
            throw $this->createNotFoundException('Could not find entity');
        }
        $form = $this->createForm(NoteType::class, $note);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $note = $form->getData();
            $note->setUserId($user->getId());
            $note->setTimestamp(new \DateTimeImmutable('NOW'));
            $this->logger->info('Note edited', [
                'note' => $note->getId(),
            ]);
            $repo->save($note);
            $this->notifier->send(new Notification('Note edited.', ['browser']));
        }

        return $this->render('page/create.html.twig', [
            'form' => $form,
            'is_edit' => true,
        ]);
    }

    #[Route('/delete/{id}', name: 'app_delete', methods: ['POST'])]
    public function remove(
        Request $request,
        int $id,
        NoteRepository $repo,
    ) {
        $user = $this->getUser();
        $token = $request->request->get('csrf_token');
        if (!$token || !$this->isCsrfTokenValid('app_delete', $token)) {
            $this->logger->warning('Invalid CSRF token', [
                'user' => $user->getId(),
            ]);
            throw $this->createAccessDeniedException('Access Denied');
        }
        $note = $repo->findOneByIdAndUserId($id, $user->getId());
        if (!$note) {
            $this->logger->warning('Attempted to delete unowned or unknown note.', [
                'user' => $user->getId(),
                'note' => $id,
            ]);
            throw $this->createNotFoundException('No entity found.');
        }
        $repo->remove($note);
        $this->logger->info('Note deleted', [
            'note' => $note->getId(),
        ]);
        $this->notifier->send(new Notification('Note deleted.', ['browser']));

        return $this->redirectToRoute('app_index');
    }
}
