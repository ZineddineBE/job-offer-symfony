<?php

namespace App\Service;

use App\Entity\Offer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUpload
{

    private SluggerInterface $slugger;
    private string $uploadsDirectory;

    public function __construct(SluggerInterface $slugger, #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadsDirectory)
    {
        $this->slugger = $slugger;
        $this->uploadsDirectory = $uploadsDirectory;
    }
    public function upload(UploadedFile $uploadedFile): string {

        $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$uploadedFile->guessExtension();

        try {
            $uploadedFile->move($this->uploadsDirectory, $newFilename);
        } catch (FileException $e) {
            throw $e;
        }

        return $newFilename;
    }

}
