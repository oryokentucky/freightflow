# AWS Deployment

Target architecture (fill in real details as you deploy — keep this honest, interviewers ask):

```
Route 53 → CloudFront → ALB → EC2 (app, 2x t3.small)
                               ├─ EC2 worker (queue:work)
                               ├─ RDS MySQL 8 (single-AZ to start)
                               └─ ElastiCache Redis
```

## Steps

1. **RDS + ElastiCache** — provision first; put both in private subnets.
2. **EC2 app instances** — Ubuntu 24.04, PHP 8.3 via `ondrej/php` PPA,
   nginx + php-fpm. User-data script or a small Ansible playbook lives in
   `infra/` (write it — it's great interview material).
3. **ALB** — health check on `/up` (Laravel's built-in health route).
4. **CloudFront** — in front of the ALB for TLS termination + caching of
   the tracking page.
5. **Deploy** — GitHub Actions job: build, run tests, then
   `rsync`/CodeDeploy to instances, `php artisan migrate --force`,
   `queue:restart`.

## Hardening checklist

- [ ] Security groups: DB/Redis reachable only from app SG
- [ ] SSM Session Manager instead of SSH keys (solves the changing-home-IP problem)
- [ ] Secrets in SSM Parameter Store, not `.env` in the repo
- [ ] CloudWatch alarms: 5xx rate, queue depth, RDS CPU
