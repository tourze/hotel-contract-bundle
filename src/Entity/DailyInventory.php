<?php

namespace Tourze\HotelContractBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineUserBundle\Attribute\UpdatedByColumn;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Entity\RoomType;

#[ORM\Entity(repositoryClass: DailyInventoryRepository::class)]
#[ORM\Table(name: 'daily_inventory', options: ['comment' => '日库存表'])]
#[ORM\Index(name: 'daily_inventory_idx_room_type_date', columns: ['room_type_id', 'date'])]
#[ORM\Index(name: 'daily_inventory_idx_hotel_date', columns: ['hotel_id', 'date'])]
#[ORM\Index(name: 'daily_inventory_idx_contract_date', columns: ['contract_id', 'date'])]
#[ORM\Index(name: 'daily_inventory_idx_date_status', columns: ['date', 'status'])]
class DailyInventory implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true, options: ['comment' => '库存唯一编码'])]
    #[Assert\NotBlank(message: '库存编码不能为空')]
    #[Assert\Length(max: 100, maxMessage: '库存编码长度不能超过 {{ limit }} 个字符')]
    private string $code = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'room_type_id', nullable: false)]
    private ?RoomType $roomType = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'hotel_id', nullable: false)]
    private ?Hotel $hotel = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, options: ['comment' => '库存日期'])]
    #[Assert\NotNull(message: '库存日期不能为空')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否为机动预留'])]
    #[Assert\Type(type: 'boolean')]
    private bool $isReserved = false;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: DailyInventoryStatusEnum::class, options: ['comment' => '库存状态'])]
    #[Assert\Choice(callback: [DailyInventoryStatusEnum::class, 'cases'])]
    private DailyInventoryStatusEnum $status = DailyInventoryStatusEnum::AVAILABLE;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'contract_id', nullable: true)]
    private ?HotelContract $contract = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['comment' => '该日采购成本价'])]
    #[Assert\PositiveOrZero]
    #[Assert\Length(max: 13)]
    private string $costPrice = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['comment' => '该日销售价格'])]
    #[Assert\PositiveOrZero]
    #[Assert\Length(max: 13)]
    private string $sellingPrice = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['comment' => '利润率'])]
    #[Assert\Length(max: 6)]
    private string $profitRate = '0.00';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '价格调整原因'])]
    #[Assert\Length(max: 255)]
    private ?string $priceAdjustReason = null;

    #[UpdatedByColumn]
    #[ORM\Column(type: Types::STRING, nullable: true, options: ['comment' => '最后修改人'])]
    #[Assert\Length(max: 255)]
    private ?string $lastModifiedBy = null;

    public function __toString(): string
    {
        $typeInfo = null !== $this->roomType ? $this->roomType->getName() : 'Unknown';
        $date = null !== $this->date ? $this->date->format('Y-m-d') : 'Unknown Date';

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

    public function setRoomType(?RoomType $roomType): void
    {
        $this->roomType = $roomType;
    }

    public function getHotel(): ?Hotel
    {
        return $this->hotel;
    }

    public function setHotel(?Hotel $hotel): void
    {
        $this->hotel = $hotel;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): void
    {
        $this->date = $date;
    }

    public function isReserved(): bool
    {
        return $this->isReserved;
    }

    public function setIsReserved(bool $isReserved): void
    {
        $this->isReserved = $isReserved;
    }

    public function getStatus(): DailyInventoryStatusEnum
    {
        return $this->status;
    }

    public function setStatus(DailyInventoryStatusEnum $status): void
    {
        $this->status = $status;
    }

    public function getContract(): ?HotelContract
    {
        return $this->contract;
    }

    public function setContract(?HotelContract $contract): void
    {
        $this->contract = $contract;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getCostPrice(): string
    {
        return $this->costPrice;
    }

    public function setCostPrice(string $costPrice): void
    {
        $this->costPrice = $costPrice;
        $this->calculateProfitRate();
    }

    public function getSellingPrice(): string
    {
        return $this->sellingPrice;
    }

    public function setSellingPrice(string $sellingPrice): void
    {
        $this->sellingPrice = $sellingPrice;
        $this->calculateProfitRate();
    }

    public function getProfitRate(): string
    {
        return $this->profitRate;
    }

    public function setProfitRate(string $profitRate): void
    {
        $this->profitRate = $profitRate;
    }

    public function getPriceAdjustReason(): ?string
    {
        return $this->priceAdjustReason;
    }

    public function setPriceAdjustReason(?string $priceAdjustReason): void
    {
        $this->priceAdjustReason = $priceAdjustReason;
    }

    public function getLastModifiedBy(): ?string
    {
        return $this->lastModifiedBy;
    }

    public function setLastModifiedBy(?string $lastModifiedBy): void
    {
        $this->lastModifiedBy = $lastModifiedBy;
    }

    /**
     * 计算利润率
     */
    private function calculateProfitRate(): void
    {
        $costPrice = (float) $this->costPrice;
        $sellingPrice = (float) $this->sellingPrice;

        if ($costPrice > 0 && $sellingPrice > 0) {
            $profitRate = ($sellingPrice - $costPrice) / $costPrice * 100;
            $this->profitRate = number_format($profitRate, 2, '.', '');
        } else {
            $this->profitRate = '0.00';
        }
    }
}
