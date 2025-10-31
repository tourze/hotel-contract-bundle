<?php

namespace Tourze\HotelContractBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\HotelContractBundle\Enum\InventorySummaryStatusEnum;
use Tourze\HotelContractBundle\Repository\InventorySummaryRepository;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Entity\RoomType;

#[ORM\Entity(repositoryClass: InventorySummaryRepository::class)]
#[ORM\Table(name: 'inventory_summary', options: ['comment' => '库存统计表'])]
#[ORM\Index(name: 'inventory_summary_idx_hotel_roomtype_date', columns: ['hotel_id', 'room_type_id', 'date'])]
#[ORM\Index(name: 'inventory_summary_idx_date_status', columns: ['date', 'status'])]
class InventorySummary implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'hotel_id', nullable: false)]
    private ?Hotel $hotel = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'room_type_id', nullable: false)]
    private ?RoomType $roomType = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, options: ['comment' => '统计日期'])]
    #[Assert\NotNull]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '该房型总房间数'])]
    #[Assert\PositiveOrZero]
    private int $totalRooms = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '可售房间数'])]
    #[Assert\PositiveOrZero]
    private int $availableRooms = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '预留房间数'])]
    #[Assert\PositiveOrZero]
    private int $reservedRooms = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '已售房间数'])]
    #[Assert\PositiveOrZero]
    private int $soldRooms = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '待确认房间数'])]
    #[Assert\PositiveOrZero]
    private int $pendingRooms = 0;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: InventorySummaryStatusEnum::class, options: ['comment' => '库存状态'])]
    #[Assert\Choice(callback: [InventorySummaryStatusEnum::class, 'cases'])]
    private InventorySummaryStatusEnum $status = InventorySummaryStatusEnum::NORMAL;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true, options: ['comment' => '当日最低采购价'])]
    #[Assert\PositiveOrZero]
    #[Assert\Length(max: 13)]
    private ?string $lowestPrice = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'lowest_contract_id', nullable: true)]
    private ?HotelContract $lowestContract = null;

    public function __toString(): string
    {
        $hotelName = null !== $this->hotel ? $this->hotel->getName() : 'Unknown';
        $roomTypeName = null !== $this->roomType ? $this->roomType->getName() : 'Unknown';
        $date = null !== $this->date ? $this->date->format('Y-m-d') : 'Unknown Date';

        return sprintf('%s - %s - %s', $hotelName, $roomTypeName, $date);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHotel(): ?Hotel
    {
        return $this->hotel;
    }

    public function setHotel(?Hotel $hotel): void
    {
        $this->hotel = $hotel;
    }

    public function getRoomType(): ?RoomType
    {
        return $this->roomType;
    }

    public function setRoomType(?RoomType $roomType): void
    {
        $this->roomType = $roomType;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): void
    {
        $this->date = $date;
    }

    public function getTotalRooms(): int
    {
        return $this->totalRooms;
    }

    public function setTotalRooms(int $totalRooms): void
    {
        $this->totalRooms = $totalRooms;
        $this->updateStatus();
    }

    public function getAvailableRooms(): int
    {
        return $this->availableRooms;
    }

    public function setAvailableRooms(int $availableRooms): void
    {
        $this->availableRooms = $availableRooms;
        $this->updateStatus();
    }

    public function getReservedRooms(): int
    {
        return $this->reservedRooms;
    }

    public function setReservedRooms(int $reservedRooms): void
    {
        $this->reservedRooms = $reservedRooms;
    }

    public function getSoldRooms(): int
    {
        return $this->soldRooms;
    }

    public function setSoldRooms(int $soldRooms): void
    {
        $this->soldRooms = $soldRooms;
        $this->updateStatus();
    }

    public function getPendingRooms(): int
    {
        return $this->pendingRooms;
    }

    public function setPendingRooms(int $pendingRooms): void
    {
        $this->pendingRooms = $pendingRooms;
    }

    public function getStatus(): InventorySummaryStatusEnum
    {
        return $this->status;
    }

    public function setStatus(InventorySummaryStatusEnum $status): void
    {
        $this->status = $status;
    }

    public function getLowestPrice(): ?string
    {
        return $this->lowestPrice;
    }

    public function setLowestPrice(?string $lowestPrice): void
    {
        $this->lowestPrice = $lowestPrice;
    }

    public function getLowestContract(): ?HotelContract
    {
        return $this->lowestContract;
    }

    public function setLowestContract(?HotelContract $lowestContract): void
    {
        $this->lowestContract = $lowestContract;
    }

    /**
     * 更新库存状态
     */
    private function updateStatus(): void
    {
        if ($this->totalRooms <= 0) {
            $this->status = InventorySummaryStatusEnum::SOLD_OUT;

            return;
        }

        $availablePercentage = ($this->availableRooms / $this->totalRooms) * 100;

        if ($this->availableRooms <= 0) {
            $this->status = InventorySummaryStatusEnum::SOLD_OUT;
        } elseif ($availablePercentage <= 10) {
            $this->status = InventorySummaryStatusEnum::WARNING;
        } else {
            $this->status = InventorySummaryStatusEnum::NORMAL;
        }
    }
}
