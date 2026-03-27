<?php

namespace App\Controller;

use App\Entity\Candidacy;
use App\Entity\Offer;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

#[Route('/api/candidacies', name: 'api_candidacies_')]
final class ApiCandidacyController extends AbstractController
{

    #[Route('/', name: 'list', methods: ['GET'])]
    #[OA\Get]
    #[OA\Response(
        response: 200,
        description: 'Returns the candidacies',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Candidacy::class, groups: ['candidacy:read']))
        )
    )]
    public function index(EntityManagerInterface $entityManager, SerializerInterface $serializer): JsonResponse
    {
        $candidacies = $entityManager->getRepository(Candidacy::class)->findAll();

        $data = $serializer->serialize($candidacies, 'json', ['groups' => ['candidacy:read']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/applyOffer/{id}', name: 'applyOffer', methods: ['POST'])]
    #[OA\Post]
    #[OA\RequestBody(
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'attachedFile', type: 'string', format: 'binary'),
                ]

            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Candidacy successfully created',
        content: new OA\JsonContent(ref: new Model(type: Candidacy::class, groups: ['candidacy:read']))
    )]
    public function applyOffer(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadsDirectory,
        SerializerInterface $serializer,
        WorkflowInterface $candidacyReviewStateMachine,
        $id): Response
    {
        $candidacy = new Candidacy();

        $user = $this->getUser();
        $candidacy->setUser($user);

        $offer = $entityManager->getRepository(Offer::class)->find($id);
        $candidacy->setOffer($offer);

        $message = $request->request->get('message');
        $candidacy->setMessage($message);

        $uploadedFile = $request->files->get('attachedFile');
        $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$uploadedFile->guessExtension();

        try {
            $uploadedFile->move($uploadsDirectory, $newFilename);
        } catch (FileException $e) {
            throw $e;
        }

        $candidacy->setAttachedFile($newFilename);

        $candidacyReviewStateMachine->getMarking($candidacy);

        $entityManager->persist($candidacy);
        $entityManager->flush();

        $data = $serializer->serialize($candidacy, 'json', ['groups' => ['candidacy:read']]);

        return new JsonResponse($data, Response::HTTP_CREATED, [], true);
    }

    #[Route('/edit-candidacy/{id}', name: 'edit_candidacy', methods: ['PUT'])]
    #[OA\Put]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'status', type: 'string'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Candidacy successfully updated',
        content: new OA\JsonContent(ref: new Model(type: Candidacy::class, groups: ['candidacy:read']))
    )]
    public function editCandidacy(Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer, $id): Response
    {
        $candidacy = $entityManager->getRepository(Candidacy::class)->find($id);

        if (!$candidacy) {
            return new JsonResponse(['message' => 'Candidacy not found'], Response::HTTP_NOT_FOUND);
        }

        $updatedCandidacy = $serializer->deserialize($request->getContent(), Candidacy::class, 'json', ['object_to_populate' => $candidacy]);

        $entityManager->persist($updatedCandidacy);
        $entityManager->flush();

        $data = $serializer->serialize($updatedCandidacy, 'json', ['groups' => ['candidacy:read']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/deleteCandidacy/{id}', name: 'deleteCandidacy', methods: ['DELETE'])]
    #[OA\Delete]
    #[OA\Response(
        response: 204,
        description: 'Candidacy successfully deleted'
    )]
    public function deleteCandidacy(Request $request, EntityManagerInterface $entityManager, $id): Response
    {
        $candidacy = $entityManager->getRepository(Candidacy::class)->find($id);

        if (!$candidacy) {
            return new JsonResponse(['message' => 'Candidacy not found'], Response::HTTP_NOT_FOUND); // J'ai corrigé "Offer not found" par "Candidacy not found" ici.
        }

        $entityManager->remove($candidacy);
        $entityManager->flush();

        return new JsonResponse(['message' => 'Candidacy deleted'], Response::HTTP_NO_CONTENT);
    }
}
