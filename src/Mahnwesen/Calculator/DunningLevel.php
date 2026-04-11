<?php

declare(strict_types=1);

namespace Accounting\Mahnwesen\Calculator;

/**
 * Dunning escalation level for an overdue invoice.
 *
 * - `None`: not yet overdue
 * - `Reminder`: gentle payment reminder (Zahlungserinnerung)
 * - `FirstDunning`: first formal dunning notice (1. Mahnung)
 * - `SecondDunning`: final dunning notice before legal escalation (2. Mahnung)
 *
 * Use `DunningLevelCalculator` to compute the current level from an invoice's
 * issue date, optional explicit due date, and the current time.
 */
enum DunningLevel: string
{
    case None = 'none';
    case Reminder = 'reminder';
    case FirstDunning = 'first_dunning';
    case SecondDunning = 'second_dunning';
}
