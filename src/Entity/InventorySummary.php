<?php

namespace Tourze\HotelContractBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
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
class InventorySummary implements Stringable
{
    use TimestampableAware;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'hotel_id', nullable: false)]
    private ?Hotel $hotel = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'room_type_id', nullable: false)]
    private ?RoomType $roomType = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, options: ['comment' => '统计日期'])]
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
    private InventorySummaryStatusEnum $status = InventorySummaryStatusEnum::NORMAL;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true, options: ['comment' => '当日最低采购价'])]
    #[Assert\PositiveOrZero]
    private ?string $lowestPrice = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'lowest_contract_id', nullable: true)]
    private ?HotelContract $lowestContract = null;


    public function __toString(): string
    {
        $hotelName = isset($this->hotel) ? $this->hotel->getName() : 'Unknown';
        $roomTypeName = isset($this->roomType) ? $this->roomType->getName() : 'Unknown';
        $date = isset($this->date) ? $this->date->format('Y-m-d') : 'Unknown Date';
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

    public function setHotel(?Hotel $hotel): self
    {
        $this->hotel = $hotel;
        return $this;
    }

    public function getRoomType(): ?RoomType
    {
        return $this->roomType;
    }

    public function setRoomType(?RoomType $roomType): self
    {
        $this->roomType = $roomType;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getTotalRooms(): int
    {
        return $this->totalRooms;
    }

    public function setTotalRooms(int $totalRooms): self
    {
        $this->totalRooms = $totalRooms;
        $this->updateStatus();
        return $this;
    }

    public function getAvailableRooms(): int
    {
        return $this->availableRooms;
    }

    public function setAvailableRooms(int $availableRooms): self
    {
        $this->availableRooms = $availableRooms;
        $this->updateStatus();
        return $this;
    }

    public function getReservedRooms(): int
    {
        return $this->reservedRooms;
    }

    public function setReservedRooms(int $reservedRooms): self
    {
        $this->reservedRooms = $reservedRooms;
        return $this;
    }

    public function getSoldRooms(): int
    {
        return $this->soldRooms;
    }

    public function setSoldRooms(int $soldRooms): self
    {
        $this->soldRooms = $soldRooms;
        $this->updateStatus();
        return $this;
    }

    public function getPendingRooms(): int
    {
        return $this->pendingRooms;
    }

    public function setPendingRooms(int $pendingRooms): self
    {
        $this->pendingRooms = $pendingRooms;
        return $this;
    }

    public function getStatus(): InventorySummaryStatusEnum
    {
        return $this->status;
    }

    public function setStatus(InventorySummaryStatusEnum $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getLowestPrice(): ?string
    {
        return $this->lowestPrice;
    }

    public function setLowestPrice(?string $lowestPrice): self
    {
        $this->lowestPrice = $lowestPrice;
        return $this;
    }

    public function getLowestContract(): ?HotelContract
    {
        return $this->lowestContract;
    }

    public function setLowestContract(?HotelContract $lowestContract): self
    {
        $this->lowestContract = $lowestContract;
        return $this;
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
