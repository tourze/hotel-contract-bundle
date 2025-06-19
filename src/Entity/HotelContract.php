<?php

namespace Tourze\HotelContractBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineUserBundle\Traits\CreatedByAware;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Enum\ContractTypeEnum;
use Tourze\HotelContractBundle\Repository\HotelContractRepository;
use Tourze\HotelProfileBundle\Entity\Hotel;

#[ORM\Entity(repositoryClass: HotelContractRepository::class)]
#[ORM\Table(name: 'hotel_contract', options: ['comment' => '酒店合同信息表'])]
#[ORM\Index(name: 'hotel_contract_idx_contract_no', columns: ['contract_no'])]
#[ORM\Index(name: 'hotel_contract_idx_hotel_id', columns: ['hotel_id'])]
class HotelContract implements Stringable
{
    use TimestampableAware;
    use CreatedByAware;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true, options: ['comment' => '合同编号'])]
    private string $contractNo = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'hotel_id', nullable: false)]
    private ?Hotel $hotel = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ContractTypeEnum::class, options: ['comment' => '合同类型'])]
    private ContractTypeEnum $contractType = ContractTypeEnum::FIXED_PRICE;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, options: ['comment' => '合同开始日期'])]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, options: ['comment' => '合同结束日期'])]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '合同总房间数'])]
    #[Assert\Positive]
    private int $totalRooms = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '合同总天数'])]
    #[Assert\Positive]
    private int $totalDays = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['comment' => '合同总金额'])]
    #[Assert\PositiveOrZero]
    private string $totalAmount = '0.00';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '合同附件URL'])]
    private ?string $attachmentUrl = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ContractStatusEnum::class, options: ['comment' => '合同状态'])]
    private ContractStatusEnum $status = ContractStatusEnum::PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '终止原因'])]
    private ?string $terminationReason = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '合同优先级'])]
    private int $priority = 0;


    #[ORM\OneToMany(targetEntity: DailyInventory::class, mappedBy: 'contract', fetch: 'EXTRA_LAZY')]
    private Collection $dailyInventories;

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

    public function setContractNo(string $contractNo): self
    {
        $this->contractNo = $contractNo;
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

    public function getContractType(): ContractTypeEnum
    {
        return $this->contractType;
    }

    public function setContractType(ContractTypeEnum $contractType): self
    {
        $this->contractType = $contractType;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getTotalRooms(): int
    {
        return $this->totalRooms;
    }

    public function setTotalRooms(int $totalRooms): self
    {
        $this->totalRooms = $totalRooms;
        return $this;
    }

    public function getTotalDays(): int
    {
        return $this->totalDays;
    }

    public function setTotalDays(int $totalDays): self
    {
        $this->totalDays = $totalDays;
        return $this;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): self
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    public function getAttachmentUrl(): ?string
    {
        return $this->attachmentUrl;
    }

    public function setAttachmentUrl(?string $attachmentUrl): self
    {
        $this->attachmentUrl = $attachmentUrl;
        return $this;
    }

    public function getStatus(): ContractStatusEnum
    {
        return $this->status;
    }

    public function setStatus(ContractStatusEnum $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getTerminationReason(): ?string
    {
        return $this->terminationReason;
    }

    public function setTerminationReason(?string $terminationReason): self
    {
        $this->terminationReason = $terminationReason;
        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }


    /**
     * @return Collection<int, DailyInventory>
     */
    public function getDailyInventories(): Collection
    {
        return $this->dailyInventories;
    }

    public function addDailyInventory(DailyInventory $dailyInventory): self
    {
        if (!$this->dailyInventories->contains($dailyInventory)) {
            $this->dailyInventories->add($dailyInventory);
            $dailyInventory->setContract($this);
        }

        return $this;
    }

    public function removeDailyInventory(DailyInventory $dailyInventory): self
    {
        if ($this->dailyInventories->removeElement($dailyInventory)) {
            if ($dailyInventory->getContract() === $this) {
                $dailyInventory->setContract(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, InventorySummary>
     */
    public function getInventorySummaries(): Collection
    {
        return $this->inventorySummaries;
    }

    public function addInventorySummary(InventorySummary $inventorySummary): self
    {
        if (!$this->inventorySummaries->contains($inventorySummary)) {
            $this->inventorySummaries->add($inventorySummary);
            $inventorySummary->setLowestContract($this);
        }

        return $this;
    }

    public function removeInventorySummary(InventorySummary $inventorySummary): self
    {
        if ($this->inventorySummaries->removeElement($inventorySummary)) {
            if ($inventorySummary->getLowestContract() === $this) {
                $inventorySummary->setLowestContract(null);
            }
        }

        return $this;
    }


    /**
     * 判断合同是否处于激活状态
     */
    public function isActive(): bool
    {
        return $this->status === ContractStatusEnum::ACTIVE;
    }

    /**
     * 计算合同总售价
     * 
     * @return float
     */
    public function getTotalSellingAmount(): float
    {
        $totalSellingAmount = 0.0;
        
        foreach ($this->dailyInventories as $inventory) {
            $totalSellingAmount += (float)$inventory->getSellingPrice();
        }
        
        return $totalSellingAmount;
    }

    /**
     * 计算合同总成本
     * 
     * @return float
     */
    public function getTotalCostAmount(): float
    {
        $totalCostAmount = 0.0;
        
        foreach ($this->dailyInventories as $inventory) {
            $totalCostAmount += (float)$inventory->getCostPrice();
        }
        
        return $totalCostAmount;
    }

    /**
     * 计算合同利润率
     * 
     * @return float
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
