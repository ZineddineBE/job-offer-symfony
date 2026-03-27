<?php

namespace App\Controller;

use App\Entity\Candidacy;
use App\Entity\Offer;
use App\Form\CandidacyType;
use App\Service\FileUpload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\WorkflowInterface;

final class CandidacyController extends AbstractController
{
    #[Route('/candidacy/{id}', name: 'candidacy')]
    public function offerDetail(Candidacy $candidacy, EntityManagerInterface $entityManager, WorkflowInterface $candidacyReviewStateMachine): Response
    {
        try {
            $candidacyReviewStateMachine->apply($candidacy, 'to_review');
            $entityManager->flush();
        } catch (LogicException $e) {
            throw $e;
        }

        return $this->render('candidacy/detail.html.twig', [
            'candidacy' => $candidacy
        ]);
    }

    #[Route('/applyOffer/{id}', name: 'applyOffer')]
    #[isGranted('ROLE_USER')]
    public function applyOffer(
        Request $request,
        EntityManagerInterface $entityManager,
        WorkflowInterface $candidacyReviewStateMachine,
        FileUpload $fileUpload,
        Offer $offer): Response
    {
        $candidacy = new Candidacy();

        $form = $this->createForm(CandidacyType::class, $candidacy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $user = $this->getUser();
            $candidacy->setUser($user);

            $candidacy->setOffer($offer);

            $uploadedFile = $form->get('attachedFile')->getData();

            //Service FileUpload
            $newFilename = $fileUpload->upload($uploadedFile);

            $candidacy->setAttachedFile($newFilename);

            $candidacyReviewStateMachine->getMarking($candidacy);

            $entityManager->persist($candidacy);
            $entityManager->flush();

            return $this->redirectToRoute('candidacies');
        }

        return $this->render('candidacy/index.html.twig', [
            'form' => $form->createView(),
            'offer' => $offer
        ]);
    }

    #[Route('/edit-candidacy/{id}', name: 'edit_candidacy')]
    public function editCandidacy(Request $request, EntityManagerInterface $entityManager, Candidacy $candidacy): Response
    {
        // On remplace $id par Candidacy $candidacy dans les paramètres de la fonction
        // Doctrine récupère automatiqueemnt l'objet $candidacy grâce à l'id de la route
        //  $candidacy = $entityManager->getRepository(Candidacy::class)->find($id);
        //
        //  if (!$candidacy) {
        //      return $this->redirectToRoute('$candidacies');
        //  }

        $form = $this->createForm(CandidacyType::class, $candidacy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($candidacy);
            $entityManager->flush();

            return $this->redirectToRoute('candidacies');
        }

        return $this->render('candidacy/index.html.twig', [
            'form' => $form->createView(),
            'candidacy' => $candidacy
        ]);
    }

    #[Route('/deleteCandidacy/{id}', name: 'deleteCandidacy')]
    public function deleteCandidacy(Request $request, EntityManagerInterface $entityManager, Candidacy $candidacy): Response
    {
        //  $candidacy = $entityManager->getRepository(Candidacy::class)->find($id);
        //
        //  if (!$candidacy) {
        //      return $this->redirectToRoute('candidacies');
        //  }

        $entityManager->remove($candidacy);
        $entityManager->flush();

        return $this->redirectToRoute('candidacies');

    }

    #[Route('/candidacies', name: 'candidacies')]
    public function candidacies(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $candidacies = $entityManager->getRepository(Candidacy::class)->findBy([
            'user' => $user
        ]);

        return $this->render('candidacy/list.html.twig', [
            'candidacies' => $candidacies
        ]);
    }
}
