# MedVoyage — API / Component Reference

All interactions are via Livewire components (server-driven). There is no separate REST API. Components are mounted on HTTP GET routes; mutations happen through Livewire actions called from the browser.

---

## Auth

### `GET /login` → `Auth\Login`
| | |
|---|---|
| Auth | Guest only |
| Actions | `authenticate()` |
| Parameters | `username: string`, `password: string`, `remember: bool` |
| Success | Redirect to `/dashboard` |
| Errors | 422 – invalid credentials, 403 – account locked/suspended |

### `GET /register` → `Auth\Register`
| | |
|---|---|
| Auth | Guest only |
| Actions | `register()` |
| Parameters | `username, email, first_name, last_name, password, password_confirmation` |
| Success | Redirect to `/dashboard`; creates User + UserProfile + MEMBER role |
| Errors | 422 – duplicate username/email, weak password |

### `POST /logout`
| | |
|---|---|
| Auth | Authenticated |
| Success | Redirect to `/login` |

### `GET /profile` → `Auth\Profile`
| | |
|---|---|
| Auth | Authenticated |
| Renders | User profile page (read-only display) |

---

## Trips

### `GET /trips` → `Trips\TripList`
| | |
|---|---|
| Auth | Authenticated |
| Actions | `search()`, `setFilter()`, `setSort()` |
| Parameters | `query: string`, `specialty: string`, `difficulty: string`, `date_from: date`, `date_to: date`, `sort: string` |
| Renders | Paginated list of PUBLISHED + FULL trips |

### `GET /trips/{trip}` → `Trips\TripDetail`
| | |
|---|---|
| Auth | Authenticated |
| Actions | `holdSeat()`, `joinWaitlist()` |
| Success (hold) | Redirect to `/trips/{trip}/signup/{signup}`; creates TripSignup HOLD |
| Success (waitlist) | Creates TripWaitlistEntry WAITING |
| Errors | 422 – no seats, already signed up / on waitlist |

### `GET /trips/{trip}/signup/{signup}` → `Trips\SignupWizard`
| | |
|---|---|
| Auth | Signup owner only |
| Actions | `nextStep()`, `prevStep()`, `submitPayment()` |
| Parameters | `emergencyContactName, emergencyContactPhone, dietaryRequirements, tenderType, referenceNumber, notes` |
| Success | Confirms TripSignup; redirect to dashboard |
| Errors | 403 – wrong user; 422 – hold expired |

### `GET /my-trips` → `Trips\MySignups`
| | |
|---|---|
| Auth | Authenticated |
| Renders | All signups for the current user |

### `GET /admin/trips/create` → `Trips\TripManage`
| | |
|---|---|
| Auth | ADMIN |
| Actions | `save()`, `publish()`, `close()`, `cancel()` |
| Parameters | `title, description, leadDoctorId, specialty, destination, startDate, endDate, difficultyLevel, prerequisites, totalSeats, priceCents` |
| Success (create) | Creates DRAFT trip |
| Success (publish) | DRAFT → PUBLISHED |
| Errors | 403 – non-admin; 422 – validation; 422 – doctor not approved |

### `GET /admin/trips/{trip}/edit` → `Trips\TripManage`
| | |
|---|---|
| Auth | ADMIN |
| Actions | `save()`, `publish()`, `close()`, `cancel()` |
| Errors | 422 – can only edit DRAFT; 409 – stale version |

---

## Reviews

### `GET /trips/{trip}/reviews/create` → `Reviews\ReviewForm`
| | |
|---|---|
| Auth | Authenticated, must have a CONFIRMED signup for the trip after it ends |
| Actions | `submit()` |
| Parameters | `rating: int (1-5)`, `reviewText: string` |
| Success | Creates TripReview; redirect to trip detail |
| Errors | 422 – not eligible, already reviewed |

### `GET /trips/{trip}/reviews/{review}/edit` → `Reviews\ReviewForm`
| | |
|---|---|
| Auth | Review author |
| Actions | `submit()` |

### `GET /admin/reviews` → `Reviews\ReviewModeration`
| | |
|---|---|
| Auth | ADMIN |
| Actions | `flag(reviewId)`, `remove(reviewId)` |
| Renders | All ACTIVE + FLAGGED reviews |

---

## Credentialing

### `GET /credentialing/profile` → `Credentialing\DoctorProfile`
| | |
|---|---|
| Auth | DOCTOR (must have doctor profile) |
| Actions | `uploadDocument()`, `submitCase()`, `resubmitCase()` |
| Parameters | `uploadFile: file`, `uploadType: DocumentType` |
| Success (upload) | Creates DoctorDocument |
| Success (submit) | Creates CredentialingCase SUBMITTED; doctor → UNDER_REVIEW |
| Success (resubmit) | Case → RE_REVIEW |
| Errors | 403 – no doctor profile; 422 – missing required documents; 422 – already has active case |

### `GET /credentialing/cases` → `Credentialing\CaseList`
| | |
|---|---|
| Auth | CREDENTIALING_REVIEWER or ADMIN |
| Renders | Paginated credentialing cases with filter/search |
| Errors | 403 – unauthorized |

### `GET /credentialing/cases/{case}` → `Credentialing\CaseDetail`
| | |
|---|---|
| Auth | CREDENTIALING_REVIEWER or ADMIN |
| Actions | `assignReviewer()`, `startReview()`, `requestMaterials()`, `approve()`, `reject()` |
| Parameters | `selectedReviewerId: uuid`, `notes: string` |
| Success (approve) | Case → APPROVED; doctor → APPROVED |
| Success (reject) | Case → REJECTED; doctor → REJECTED |
| Errors | 403 – unauthorized; 422 – invalid transition |

### `GET /credentialing/documents/{document}/download`
| | |
|---|---|
| Auth | Document owner (doctor) or CREDENTIALING_REVIEWER or ADMIN |
| Success | Streams file download |
| Errors | 403 – unauthorized; 404 – file missing |

---

## Membership

### `GET /membership` → `Membership\PlanCatalog`
| | |
|---|---|
| Auth | Authenticated |
| Renders | All active membership plans |

### `GET /membership/my` → `Membership\MyMembership`
| | |
|---|---|
| Auth | Authenticated |
| Renders | Current user's active membership and order history |

### `GET /membership/purchase/{plan}` → `Membership\PurchaseFlow`
| | |
|---|---|
| Auth | Authenticated (no active membership) |
| Actions | `purchase()` |
| Success | Creates MembershipOrder PENDING; redirect to my membership |
| Errors | 422 – already has active membership; 422 – plan inactive |

### `GET /membership/top-up/{plan}` → `Membership\TopUpFlow`
| | |
|---|---|
| Auth | Authenticated (active membership within 30-day top-up window) |
| Actions | `topUp()` |
| Success | Creates TOP_UP order PENDING (price diff only) |
| Errors | 422 – no active membership; 422 – window expired; 422 – downgrade not allowed |

### `GET /membership/orders/{order}/refund` → `Membership\RefundRequest`
| | |
|---|---|
| Auth | Authenticated (order owner) |
| Actions | `submit()` |
| Parameters | `refundType: full|partial`, `reason: string`, `amountCents?: int` |
| Success | Creates Refund PENDING |
| Errors | 422 – order not PAID; 422 – reason too short |

### `GET /membership/orders` → `Membership\OrderHistory`
| | |
|---|---|
| Auth | Authenticated |
| Success | Redirects to `/membership/my` |

---

## Finance

### `GET /finance` → `Finance\FinanceDashboard`
| | |
|---|---|
| Auth | FINANCE_SPECIALIST or ADMIN |
| Renders | Daily settlement summary, recent payments |

### `GET /finance/payments` → `Finance\PaymentIndex`
| | |
|---|---|
| Auth | FINANCE_SPECIALIST or ADMIN |
| Renders | Paginated payments with filter by status/date |

### `GET /finance/payments/record` → `Finance\PaymentRecord`
| | |
|---|---|
| Auth | FINANCE_SPECIALIST or ADMIN |
| Actions | `submit()` |
| Parameters | `selectedUserId: uuid`, `tenderType: TenderType`, `amountInput: string`, `referenceNumber?: string` |
| Success | Creates Payment RECORDED; redirect to payment detail |
| Errors | 422 – validation |

### `GET /finance/payments/{payment}` → `Finance\PaymentDetail`
| | |
|---|---|
| Auth | FINANCE_SPECIALIST or ADMIN |
| Actions | `confirm()`, `void()` |
| Parameters | `confirmationEventId: string` |
| Success (confirm) | RECORDED → CONFIRMED; cascades to linked membership order → PAID |
| Success (void) | → VOIDED; cascades to linked signup (cancel) or membership order (void) |
| Errors | 422 – wrong status; 409 – event ID already used |

### `GET /finance/settlements` → `Finance\SettlementIndex`
| | |
|---|---|
| Auth | FINANCE_SPECIALIST or ADMIN |
| Renders | All settlements; shows variance and status badges |

### `GET /finance/settlements/{settlement}` → `Finance\SettlementDetail`
| | |
|---|---|
| Auth | FINANCE_SPECIALIST or ADMIN |
| Actions | `close()`, `resolveException()`, `reReconcile()` |
| Success (close) | → RECONCILED (variance ≤ 1 cent) or EXCEPTION |
| Success (resolve) | Exception → RESOLVED or WRITTEN_OFF |
| Success (reReconcile) | All exceptions resolved → RECONCILED |
| Errors | 422 – already reconciled; 422 – open exceptions remain |

### `GET /finance/invoices` → `Finance\InvoiceIndex`
| | |
|---|---|
| Auth | FINANCE_SPECIALIST or ADMIN |
| Renders | All invoices |

### `GET /finance/invoices/create` → `Finance\InvoiceBuilder`
| | |
|---|---|
| Auth | FINANCE_SPECIALIST or ADMIN |
| Actions | `save()`, `send()`, `void()`, `markPaid()` |
| Parameters | `recipientUserId, dueDate, lineItems[]: {description, type, amountCents}` |
| Errors | 422 – validation |

### `GET /finance/invoices/{invoice}` → `Finance\InvoiceDetail`
| | |
|---|---|
| Auth | FINANCE_SPECIALIST or ADMIN |
| Actions | `send()`, `void()`, `markPaid()` |

### `GET /finance/invoices/{invoice}/edit` → `Finance\InvoiceBuilder`
| | |
|---|---|
| Auth | FINANCE_SPECIALIST or ADMIN |
| Errors | 422 – cannot edit sent/paid invoices |

### `GET /finance/statements/export` → `Finance\StatementExport`
| | |
|---|---|
| Auth | FINANCE_SPECIALIST or ADMIN |
| Actions | `export()` |
| Parameters | `dateFrom: date`, `dateTo: date`, `format: csv|pdf` |
| Success | Downloads settlement statement file |

### `GET /finance/refunds` → `Membership\RefundApproval`
| | |
|---|---|
| Auth | FINANCE_SPECIALIST or ADMIN |
| Actions | `approve(refundId)`, `reject(refundId)` |
| Success (approve) | Refund → APPROVED |

---

## Search & Recommendations

### `GET /search` → `Search\TripSearch`
| | |
|---|---|
| Auth | Authenticated |
| Actions | `search()`, `typeAhead()`, `selectSuggestion()`, `clearHistory()` |
| Parameters | `query: string`, `specialty, difficulty, date_from, date_to: string`, `sort: string` |
| Renders | Filtered, sorted PUBLISHED trips; type-ahead suggestions; search history |
| Errors | None (empty results on no match) |

### `GET /recommendations` → `Search\Recommendations`
| | |
|---|---|
| Auth | Authenticated |
| Renders | Labeled recommendation sections (MostBooked, SimilarSpecialty, UpcomingSoonest) driven by `config/recommendations.php` |

---

## Admin

### `GET /admin/users` → `Admin\UserList`
| | |
|---|---|
| Auth | ADMIN |
| Actions | `updatedSearch()`, `updatedFilterStatus()`, `updatedFilterRole()` |
| Renders | Paginated user list with search/filter |
| Errors | 403 – non-admin |

### `GET /admin/users/{user}` → `Admin\UserDetail`
| | |
|---|---|
| Auth | ADMIN |
| Actions | `activate()`, `suspend()`, `lock()`, `unlock()`, `addRole()`, `removeRole()` |
| Errors | 403 – non-admin; 422 – invalid transition; 409 – stale version |

### `GET /admin/audit` → `Admin\AuditLogViewer`
| | |
|---|---|
| Auth | ADMIN |
| Renders | Paginated audit log with filter by event type and date |

### `GET /admin/config` → `Admin\SystemConfig`
| | |
|---|---|
| Auth | ADMIN |
| Renders | System configuration values (seat hold duration, waitlist offer duration) |

---

## Scheduled Commands

| Command | Schedule | Purpose |
|---|---|---|
| `medvoyage:expire-seat-holds` | Every minute | Releases HOLD signups past `hold_expires_at`; triggers waitlist offer |
| `medvoyage:expire-waitlist-offers` | Every minute | Cancels OFFERED waitlist entries past `offer_expires_at` |
| `medvoyage:check-license-expiry` | Daily | Flags doctors with licenses expiring within 30 days |
| `medvoyage:close-daily-settlement` | Daily at 23:59 | Closes and reconciles the day's settlement |
| `medvoyage:reconcile-seats` | Every 5 minutes | Re-syncs `available_seats` from active signup counts |

---

## Error Conventions

| HTTP | Meaning |
|---|---|
| 403 | Role/ownership gate failed (Livewire `assertForbidden`) |
| 404 | Resource not found (Eloquent model binding) |
| 409 | Stale version (optimistic lock) or idempotency conflict |
| 422 | Business rule violated (invalid status transition, validation failure) |
