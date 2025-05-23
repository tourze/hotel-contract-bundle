<?php

namespace Tourze\HotelContractBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineTimestampBundle\Attribute\CreateTimeColumn;
use Tourze\DoctrineTimestampBundle\Attribute\UpdateTimeColumn;
use Tourze\DoctrineUserBundle\Attribute\UpdatedByColumn;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Entity\RoomType;

#[ORM\Entity(repositoryClass: DailyInventoryRepository::class)]
#[ORM\Table(name: 'daily_inventory', options: ['comment' => '日库存表'])]
#[ORM\Index(name: 'daily_inventory_idx_roomtype_date', columns: ['room_type_id', 'date'])]
#[ORM\Index(name: 'daily_inventory_idx_hotel_date', columns: ['hotel_id', 'date'])]
#[ORM\Index(name: 'daily_inventory_idx_contract_date', columns: ['contract_id', 'date'])]
#[ORM\Index(name: 'daily_inventory_idx_date_status', columns: ['date', 'status'])]
class DailyInventory implements Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true, options: ['comment' => '库存唯一编码'])]
    private string $code = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'room_type_id', nullable: false)]
    private ?RoomType $roomType = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'hotel_id', nullable: false)]
    private ?Hotel $hotel = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, options: ['comment' => '库存日期'])]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否为机动预留'])]
    private bool $isReserved = false;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: DailyInventoryStatusEnum::class, options: ['comment' => '库存状态'])]
    private DailyInventoryStatusEnum $status = DailyInventoryStatusEnum::AVAILABLE;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'contract_id', nullable: true)]
    private ?HotelContract $contract = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['comment' => '该日采购成本价'])]
    #[Assert\PositiveOrZero]
    private string $costPrice = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['comment' => '该日销售价格'])]
    #[Assert\PositiveOrZero]
    private string $sellingPrice = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['comment' => '利润率'])]
    private string $profitRate = '0.00';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '价格调整原因'])]
    private ?string $priceAdjustReason = null;

    #[CreateTimeColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createTime = null;

    #[UpdateTimeColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updateTime = null;

    #[UpdatedByColumn]
    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?int $lastModifiedBy = null;

    public function __toString(): string
    {
        $typeInfo = $this->roomType ? $this->roomType->getName() : 'Unknown';
        $date = $this->date ? $this->date->format('Y-m-d') : 'Unknown Date';
        return sprintf('%s - %s - %s', $typeInfo, $this->code, $date);
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getHotel(): ?Hotel
    {
        return $this->hotel;
    }

    public function setHotel(?Hotel $hotel): self
    {
        $this->hotel = $hotel;
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

    public function isReserved(): bool
    {
        return $this->isReserved;
    }

    public function setIsReserved(bool $isReserved): self
    {
        $this->isReserved = $isReserved;
        return $this;
    }

    public function getStatus(): DailyInventoryStatusEnum
    {
        return $this->status;
    }

    public function setStatus(DailyInventoryStatusEnum $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getContract(): ?HotelContract
    {
        return $this->contract;
    }

    public function setContract(?HotelContract $contract): self
    {
        $this->contract = $contract;
        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getCostPrice(): string
    {
        return $this->costPrice;
    }

    public function setCostPrice(string $costPrice): self
    {
        $this->costPrice = $costPrice;
        $this->calculateProfitRate();
        return $this;
    }

    public function getSellingPrice(): string
    {
        return $this->sellingPrice;
    }

    public function setSellingPrice(string $sellingPrice): self
    {
        $this->sellingPrice = $sellingPrice;
        $this->calculateProfitRate();
        return $this;
    }

    public function getProfitRate(): string
    {
        return $this->profitRate;
    }

    public function setProfitRate(string $profitRate): self
    {
        $this->profitRate = $profitRate;
        return $this;
    }

    public function getPriceAdjustReason(): ?string
    {
        return $this->priceAdjustReason;
    }

    public function setPriceAdjustReason(?string $priceAdjustReason): self
    {
        $this->priceAdjustReason = $priceAdjustReason;
        return $this;
    }

    public function getCreateTime(): ?\DateTimeInterface
    {
        return $this->createTime;
    }

    public function getUpdateTime(): ?\DateTimeInterface
    {
        return $this->updateTime;
    }

    public function getLastModifiedBy(): ?int
    {
        return $this->lastModifiedBy;
    }

    /**
     * 计算利润率
     */
    private function calculateProfitRate(): void
    {
        $costPrice = (float)$this->costPrice;
        $sellingPrice = (float)$this->sellingPrice;

        if ($costPrice > 0 && $sellingPrice > 0) {
            $profitRate = ($sellingPrice - $costPrice) / $costPrice * 100;
            $this->profitRate = number_format($profitRate, 2, '.', '');
        } else {
            $this->profitRate = '0.00';
        }
    }

    public function setCreateTime(?\DateTimeInterface $createTime): void
    {
        $this->createTime = $createTime;
    }

    public function setUpdateTime(?\DateTimeInterface $updateTime): void
    {
        $this->updateTime = $updateTime;
    }
}
