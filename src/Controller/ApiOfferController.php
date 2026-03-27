<?php

namespace App\Controller;

use App\Entity\Offer;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\Workflow\WorkflowInterface;

#[Route('/api/offers', name: 'api_offers_')]
final class ApiOfferController extends AbstractController
{
    #[Route('/', name: 'list', methods: ['GET'])]
    #[OA\Get]
    #[OA\Response(
        response: 200,
        description: 'Returns the offers',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Offer::class, groups: ['offer:read']))
        )
    )]
    public function index(EntityManagerInterface $entityManager, SerializerInterface $serializer): JsonResponse
    {
        $offers = $entityManager->getRepository(Offer::class)->findAll();
        $data = $serializer->serialize($offers, 'json', ['groups' => ['offer:read']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/add-offer', name: 'add_offer', methods: ['POST'])]
    #[isGranted("ROLE_ADMIN")]
    #[OA\Post]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'tags', type: 'string'),
            ]
        )
    )]
    public function addOffer(Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer, WorkflowInterface $offerReviewStateMachine): JsonResponse
    {
        $offer = $serializer->deserialize($request->getContent(), Offer::class, 'json');

        $user = $this->getUser();
        $offer->setUser($user);

        $offerReviewStateMachine->getMarking($offer);

        $entityManager->persist($offer);
        $entityManager->flush();

        $data = $serializer->serialize($offer, 'json', ['groups' => ['offer:read']]);

        return new JsonResponse($data, Response::HTTP_CREATED, [], true);
    }

    #[Route('/edit-offer/{id}', name: 'edit_offer', methods: ['PUT'])]
    #[isGranted("ROLE_ADMIN")]
    #[OA\Put]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'tags', type: 'string'),
            ]
        )
    )]
    public function editOffer(Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer, $id): JsonResponse
    {
        $offer = $entityManager->getRepository(Offer::class)->find($id);

        if (!$offer) {
            return new JsonResponse(['message' => 'Offer not found'], Response::HTTP_NOT_FOUND);
        }

        $updatedOffer = $serializer->deserialize($request->getContent(), Offer::class, 'json', ['object_to_populate' => $offer]);

        $entityManager->persist($updatedOffer);
        $entityManager->flush();

        $data = $serializer->serialize($updatedOffer, 'json', ['groups' => ['offer:read']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);

    }

    #[Route('/deleteOffer/{id}', name: 'deleteOffer', methods: ['DELETE'])]
    #[isGranted("ROLE_ADMIN")]
    public function deleteOffer(Request $request, EntityManagerInterface $entityManager, $id): JsonResponse
    {
        $offer = $entityManager->getRepository(Offer::class)->find($id);

        if (!$offer) {
            return new JsonResponse(['message' => 'Offer not found'], Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($offer);
        $entityManager->flush();

        return new JsonResponse(['message' => 'Offer deleted'], Response::HTTP_NO_CONTENT);

    }
}
