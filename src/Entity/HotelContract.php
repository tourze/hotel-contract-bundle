<?php

namespace Tourze\HotelContractBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineUserBundle\Traits\CreatedByAware;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Enum\ContractTypeEnum;
use Tourze\HotelContractBundle\Repository\HotelContractRepository;
use Tourze\HotelProfileBundle\Entity\Hotel;

#[ORM\Entity(repositoryClass: HotelContractRepository::class)]
#[ORM\Table(name: 'hotel_contract', options: ['comment' => '酒店合同信息表'])]
#[ORM\Index(name: 'hotel_contract_idx_hotel_priority', columns: ['hotel_id', 'priority'])]
class HotelContract implements \Stringable
{
    use TimestampableAware;
    use CreatedByAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 50, unique: true, options: ['comment' => '合同编号'])]
    #[Assert\NotBlank(message: '合同编号不能为空')]
    #[Assert\Length(max: 50, maxMessage: '合同编号长度不能超过 {{ limit }} 个字符')]
    private string $contractNo = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'hotel_id', nullable: false)]
    private ?Hotel $hotel = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ContractTypeEnum::class, options: ['comment' => '合同类型'])]
    #[Assert\Choice(callback: [ContractTypeEnum::class, 'cases'])]
    private ContractTypeEnum $contractType = ContractTypeEnum::FIXED_PRICE;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, options: ['comment' => '合同开始日期'])]
    #[Assert\NotNull(message: '合同开始日期不能为空')]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, options: ['comment' => '合同结束日期'])]
    #[Assert\NotNull(message: '合同结束日期不能为空')]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '合同总房间数'])]
    #[Assert\Positive]
    private int $totalRooms = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '合同总天数'])]
    #[Assert\Positive]
    private int $totalDays = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['comment' => '合同总金额'])]
    #[Assert\PositiveOrZero]
    #[Assert\Length(max: 13)]
    private string $totalAmount = '0.00';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '合同附件URL'])]
    #[Assert\Length(max: 255)]
    #[Assert\Url]
    private ?string $attachmentUrl = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ContractStatusEnum::class, options: ['comment' => '合同状态'])]
    #[Assert\Choice(callback: [ContractStatusEnum::class, 'cases'])]
    private ContractStatusEnum $status = ContractStatusEnum::PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '终止原因'])]
    #[Assert\Length(max: 65535)]
    private ?string $terminationReason = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '合同优先级'])]
    #[Assert\Range(min: 0, max: 999)]
    private int $priority = 0;

    /**
     * @var Collection<int, DailyInventory>
     */
    #[ORM\OneToMany(targetEntity: DailyInventory::class, mappedBy: 'contract', fetch: 'EXTRA_LAZY')]
    private Collection $dailyInventories;

    /**
     * @var Collection<int, InventorySummary>
     */
    #[ORM\OneToMany(targetEntity: InventorySummary::class, mappedBy: 'lowestContract', fetch: 'EXTRA_LAZY')]
    private Collection $inventorySummaries;

    public function __construct()
    {
        $this->dailyInventories = new ArrayCollection();
        $this->inventorySummaries = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->contractNo;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContractNo(): string
    {
        return $this->contractNo;
    }

    public function setContractNo(string $contractNo): void
    {
        $this->contractNo = $contractNo;
    }

    public function getHotel(): ?Hotel
    {
        return $this->hotel;
    }

    public function setHotel(?Hotel $hotel): void
    {
        $this->hotel = $hotel;
    }

    public function getContractType(): ContractTypeEnum
    {
        return $this->contractType;
    }

    public function setContractType(ContractTypeEnum $contractType): void
    {
        $this->contractType = $contractType;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): void
    {
        $this->endDate = $endDate;
    }

    public function getTotalRooms(): int
    {
        return $this->totalRooms;
    }

    public function setTotalRooms(int $totalRooms): void
    {
        $this->totalRooms = $totalRooms;
    }

    public function getTotalDays(): int
    {
        return $this->totalDays;
    }

    public function setTotalDays(int $totalDays): void
    {
        $this->totalDays = $totalDays;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): void
    {
        $this->totalAmount = $totalAmount;
    }

    public function getAttachmentUrl(): ?string
    {
        return $this->attachmentUrl;
    }

    public function setAttachmentUrl(?string $attachmentUrl): void
    {
        $this->attachmentUrl = $attachmentUrl;
    }

    public function getStatus(): ContractStatusEnum
    {
        return $this->status;
    }

    public function setStatus(ContractStatusEnum $status): void
    {
        $this->status = $status;
    }

    public function getTerminationReason(): ?string
    {
        return $this->terminationReason;
    }

    public function setTerminationReason(?string $terminationReason): void
    {
        $this->terminationReason = $terminationReason;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    /**
     * @return Collection<int, DailyInventory>
     */
    public function getDailyInventories(): Collection
    {
        return $this->dailyInventories;
    }

    public function addDailyInventory(DailyInventory $dailyInventory): void
    {
        if (!$this->dailyInventories->contains($dailyInventory)) {
            $this->dailyInventories->add($dailyInventory);
            $dailyInventory->setContract($this);
        }
    }

    public function removeDailyInventory(DailyInventory $dailyInventory): void
    {
        if ($this->dailyInventories->removeElement($dailyInventory)) {
            if ($dailyInventory->getContract() === $this) {
                $dailyInventory->setContract(null);
            }
        }
    }

    /**
     * @return Collection<int, InventorySummary>
     */
    public function getInventorySummaries(): Collection
    {
        return $this->inventorySummaries;
    }

    public function addInventorySummary(InventorySummary $inventorySummary): void
    {
        if (!$this->inventorySummaries->contains($inventorySummary)) {
            $this->inventorySummaries->add($inventorySummary);
            $inventorySummary->setLowestContract($this);
        }
    }

    public function removeInventorySummary(InventorySummary $inventorySummary): void
    {
        if ($this->inventorySummaries->removeElement($inventorySummary)) {
            if ($inventorySummary->getLowestContract() === $this) {
                $inventorySummary->setLowestContract(null);
            }
        }
    }

    /**
     * 判断合同是否处于激活状态
     */
    public function isActive(): bool
    {
        return ContractStatusEnum::ACTIVE === $this->status;
    }

    /**
     * 计算合同总售价
     */
    public function getTotalSellingAmount(): float
    {
        $totalSellingAmount = 0.0;

        foreach ($this->dailyInventories as $inventory) {
            $totalSellingAmount += (float) $inventory->getSellingPrice();
        }

        return $totalSellingAmount;
    }

    /**
     * 计算合同总成本
     */
    public function getTotalCostAmount(): float
    {
        $totalCostAmount = 0.0;

        foreach ($this->dailyInventories as $inventory) {
            $totalCostAmount += (float) $inventory->getCostPrice();
        }

        return $totalCostAmount;
    }

    /**
     * 计算合同利润率
     */
    public function getProfitRate(): float
    {
        $totalCost = $this->getTotalCostAmount();
        $totalSelling = $this->getTotalSellingAmount();

        if ($totalCost > 0) {
            return (($totalSelling - $totalCost) / $totalCost) * 100;
        }

        return 0.0;
    }
}
