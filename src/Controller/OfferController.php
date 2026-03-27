<?php

namespace App\Controller;

use App\Entity\Candidacy;
use App\Entity\Offer;
use App\Form\OfferType;
use App\Service\UpdateOffer;
use App\Service\UpdateOffers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\WorkflowInterface;

final class OfferController extends AbstractController
{

    #[Route('/offer/{id}', name: 'offer')]
    public function offerDetail(Offer $offer, EntityManagerInterface $entityManager, WorkflowInterface $offerReviewStateMachine): Response
    {
        try {
            $offerReviewStateMachine->apply($offer, 'submit_for_review');
            $entityManager->flush();
        } catch (LogicException $e) {
            throw $e;
        }

        return $this->render('offer/detail.html.twig', [
            'offer' => $offer
        ]);
    }

    #[Route('/add-offer', name: 'add_offer')]
    #[isGranted("ROLE_ADMIN")]
    public function addOffer(Request $request, EntityManagerInterface $entityManager, WorkflowInterface $offerReviewStateMachine): Response
    {
        $offer = new Offer();
        $form = $this->createForm(OfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            $offer->setUser($user);

            $offerReviewStateMachine->getMarking($offer);

            $entityManager->persist($offer);
            $entityManager->flush();

            return $this->redirectToRoute('offers');
        }

        return $this->render('offer/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/edit-offer/{id}', name: 'edit_offer')]
    #[isGranted("ROLE_ADMIN")]
    public function editOffer(Request $request, EntityManagerInterface $entityManager, Offer $offer): Response
    {
        // On remplace $id par Offer $offer dans les paramètres de la fonction
        // Doctrine récupère automatiqueemnt l'objet $offer grâce à l'id de la route
        // $offer = $entityManager->getRepository(Offer::class)->find($id);

        // if (!$offer) {
        //     return $this->redirectToRoute('offers');
        // }

        $form = $this->createForm(OfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($offer);
            $entityManager->flush();

            return $this->redirectToRoute('offers');
        }

        return $this->render('offer/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/deleteOffer/{id}', name: 'deleteOffer')]
    #[isGranted("ROLE_ADMIN")]
    public function deleteOffer(Request $request, EntityManagerInterface $entityManager, Offer $offer): Response
    {
        $entityManager->remove($offer);
        $entityManager->flush();

        return $this->redirectToRoute('offers');
    }

    #[Route('/offers', name: 'offers')]
    public function offers(EntityManagerInterface $entityManager, UpdateOffers $updateOffers): Response
    {
        $offers = $entityManager->getRepository(Offer::class)->findAll();

        $updateOffers->update($offers);

        return $this->render('offer/list.html.twig', ['offers' => $offers]);
    }
}
