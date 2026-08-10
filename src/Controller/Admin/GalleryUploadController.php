<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Service\ImageOptimizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class GalleryUploadController extends AbstractController
{
    #[Route('/admin/produits/{id}/photos', name: 'admin_gallery_upload')]
    #[IsGranted('ROLE_ADMIN')]
    public function upload(Product $product, Request $request, EntityManagerInterface $em, ImageOptimizer $imageOptimizer): Response
    {
        if ($request->isMethod('POST')) {
            /** @var UploadedFile[] $files */
            $files = $request->files->get('images') ?? [];

            $maxPosition = 0;
            foreach ($product->getProductImages() as $img) {
                $maxPosition = max($maxPosition, $img->getPosition());
            }

            $destination = $this->getParameter('kernel.project_dir') . '/public/images/products';

            foreach ($files as $file) {
                $newFilename = uniqid() . '.' . $file->guessExtension();
                $file->move($destination, $newFilename);
                $imageOptimizer->optimize($destination . '/' . $newFilename);

                $maxPosition++;
                $productImage = new ProductImage();
                $productImage->setProduct($product);
                $productImage->setFilename($newFilename);
                $productImage->setPosition($maxPosition);
                $em->persist($productImage);
            }

            $em->flush();

            $this->addFlash('success', count($files) . ' photo(s) ajoutée(s) avec succès.');

            return $this->redirectToRoute('admin_gallery_upload', ['id' => $product->getId()]);
        }

        return $this->render('admin/gallery_upload.html.twig', [
            'product' => $product,
        ]);
    }
}