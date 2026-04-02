# Temporal.io Integration (Laravel + PHP SDK)

This repository includes:
- A **Temporal Server + UI** in `docker-compose.yml`
- The **Temporal PHP SDK** (`temporal/sdk`) in `src/composer.json`
- A **RoadRunner worker** config + entrypoint to execute Workflows/Activities
- A set of **example Workflows** showing common Temporal use cases

For a deep dive into the **current examples** (signals, queries, activities, flows) and a guide on how to add more, see:
- [`TEMPORAL_EXAMPLES.md`](TEMPORAL_EXAMPLES.md)

## Prerequisites

- Docker Desktop
- `docker-compose` running from repo root

## Services

- **Temporal Server**: `localhost:7233`
- **Temporal UI**: `localhost:8233`

## Using an external Temporal service (non-docker)

If you need to connect this app/worker to a **remote/external Temporal Frontend** (e.g., a Temporal cluster outside this repo), update **both**:

1) **Laravel API client connection** (used by `TemporalExamplesController`)

- Set env vars (preferred):
  - `TEMPORAL_ADDRESS=<host:port>`
  - `TEMPORAL_NAMESPACE=<namespace>`
  - `TEMPORAL_TASK_QUEUE=<task-queue>`

These are read from `src/config/temporal.php` and wired in `src/app/Providers/TemporalServiceProvider.php` via:
- `ServiceClient::create(config('temporal.address'))`
- `ClientOptions()->withNamespace(config('temporal.namespace'))`

2) **RoadRunner worker connection**

- Update `src/.rr.yaml`:
  - `temporal.address: "<host:port>"`

Notes:
- If your worker runs **inside Docker**, `127.0.0.1:7233` will point to the container itself (not your host). Use a reachable hostname/IP for your environment.
- This repo does **not** currently document/configure TLS/mTLS or Temporal Cloud auth; if your external Temporal requires TLS, we’ll need to extend the configuration accordingly.

## How to run

### 1) Start the stack

From repo root:

```bash
docker-compose up -d --build
```

### 2) Start the Temporal PHP Worker (RoadRunner)

From repo root:

```bash
docker exec -it laravel_php php -v
docker exec -it laravel_php ./vendor/bin/rr serve -c .rr.yaml
```

Notes:
- RoadRunner config lives at `src/.rr.yaml`
- Worker entrypoint is `src/worker.php`
- The task queue is `TEMPORAL_TASK_QUEUE` (defaults to `laravel-template`)

## Example use cases (with endpoints)

All example endpoints are **JWT-protected** and live under:
- `/api/temporal/examples/*`

### 1) Workflow + Activity

- **Workflow**: `App\Temporal\Workflows\GreetingWorkflow`
- **Activity**: `App\Temporal\Activities\GreetingActivity`
- **Endpoint**: `POST /api/temporal/examples/greeting/start`

Body:
- `name` (optional)
- `workflow_id` (optional)

### 2) Signals + Queries (+ await/timer)

- **Workflow**: `App\Temporal\Workflows\OrderWorkflow`
- **Activity**: `App\Temporal\Activities\OrderActivity`
- **Start**: `POST /api/temporal/examples/order/start`
- **Signal (add item)**: `POST /api/temporal/examples/order/{workflowId}/signal/add-item`
- **Signal (cancel)**: `POST /api/temporal/examples/order/{workflowId}/signal/cancel`
- **Query (status)**: `GET /api/temporal/examples/order/{workflowId}/query/status`

### 3) Child Workflows

- **Parent**: `App\Temporal\Workflows\ParentWorkflow`
- **Child**: `App\Temporal\Workflows\ChildWorkflow`
- **Endpoint**: `POST /api/temporal/examples/child/start`

### 4) Activity retries

- **Workflow**: `App\Temporal\Workflows\RetryWorkflow`
- **Activity**: `App\Temporal\Activities\FlakyActivity`
- **Endpoint**: `POST /api/temporal/examples/retry/start`

Body:
- `succeed_on_attempt` (default `3`)

### 5) Continue-As-New (history control)

- **Workflow**: `App\Temporal\Workflows\ContinueAsNewWorkflow`
- **Endpoint**: `POST /api/temporal/examples/continue-as-new/start`

Body:
- `items` (default `[1..10]`)
- `batch_size` (default `5`)

### 6) Saga / compensations

- **Workflow**: `App\Temporal\Workflows\SagaWorkflow`
- **Activities**: `App\Temporal\Activities\PaymentActivity`
- **Endpoint**: `POST /api/temporal/examples/saga/start`

Body:
- `order_id` (optional)
- `amount_cents` (default `50`)

This workflow intentionally fails when `amount_cents < 100` to demonstrate compensations.

### 7) Payment status monitor (ERP /payment/check-status)

- **Workflow**: `App\Temporal\Workflows\PaymentStatusMonitorWorkflow`
- **Activities**: `App\Temporal\Activities\ErpPaymentActivity`
- **Start**: `POST /api/temporal/examples/payment-monitor/start`
- **Signal (webhook received)**: `POST /api/temporal/examples/payment-monitor/{workflowId}/signal/webhook`
- **Query (status)**: `GET /api/temporal/examples/payment-monitor/{workflowId}/query/status`

This example shows how to call the real ERP Payment API via an Activity while keeping Workflow code deterministic.

## Where to look in the UI

Open Temporal UI at `http://localhost:8233` and filter by:
- Task Queue: `laravel-template`
- Workflow types: `GreetingWorkflow`, `OrderWorkflow`, `RetryWorkflow`, etc.


