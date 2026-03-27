<?php

namespace App\Service;

use App\Entity\Offer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class UpdateOffers
{
    private EntityManagerInterface $entityManager;

    private WorkflowInterface $offerReviewStateMachine;

    public function __construct(EntityManagerInterface $entityManager, WorkflowInterface $offerReviewStateMachine) {
        $this->entityManager = $entityManager;
        $this->offerReviewStateMachine = $offerReviewStateMachine;
    }

    public function update(array $offers): void {
        foreach ($offers as $offer) {
            if ($this->offerReviewStateMachine->can($offer, 'publish')) {
                $this->offerReviewStateMachine->apply($offer, 'publish');
            } elseif ($this->offerReviewStateMachine->can($offer, 'submit_for_review')) {
                $this->offerReviewStateMachine->apply($offer, 'submit_for_review');
            }
        }
        $this->entityManager->flush();
    }

}
