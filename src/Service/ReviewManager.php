<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\Review;
use App\Entity\User;
use App\Repository\CustomerOrderRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReviewManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ReviewRepository $reviewRepository,
        private CustomerOrderRepository $orderRepository,
    ) {
    }

    public function hasPurchased(User $user, Product $product): bool
{
    return $this->orderRepository->hasUserPurchasedProduct($user, $product);
}

    public function hasAlreadyReviewed(User $user, Product $product): bool
    {
        return $this->reviewRepository->findOneBy(['user' => $user, 'product' => $product]) !== null;
    }

    public function addReview(User $user, Product $product, int $rating, string $comment): Review
    {
        $review = new Review();
        $review->setUser($user);
        $review->setProduct($product);
        $review->setRating(max(1, min(5, $rating)));
        $review->setComment($comment);
        $review->setIsVerified($this->hasPurchased($user, $product));
        $review->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($review);
        $this->entityManager->flush();

        return $review;
    }

    public function getAverageRating(Product $product): float
    {
        $reviews = $product->getReviews();

        if (count($reviews) === 0) {
            return 0;
        }

        $total = 0;
        foreach ($reviews as $review) {
            $total += $review->getRating();
        }

        return round($total / count($reviews), 1);
    }

        public function deleteReview(User $user, Review $review): void
{
    if ($review->getUser() !== $user) {
        throw new \LogicException('Tu ne peux supprimer que tes propres avis.');
    }

    $this->entityManager->remove($review);
    $this->entityManager->flush();
}
}