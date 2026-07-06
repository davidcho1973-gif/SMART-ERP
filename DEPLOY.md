# 배포 가이드 (NAHSHON MEP · SMART-ERP)

## 브랜치 흐름

| 환경 | 브랜치 | 용도 |
|---|---|---|
| **Staging** | `main` | 개발 결과 확인 · 데모 데이터 · 매 배포마다 DB 초기화 가능 |
| **Production** | `production` | 실제 운영 · 실데이터 · DB는 절대 초기화하지 않음 |

- 평소 개발 → `main` 푸시 → staging 자동 배포 → 확인
- 검증이 끝나면 → `production` 브랜치로 머지/푸시 → production 자동 배포

```bash
# 검증 완료된 main을 production으로 승격
git checkout production
git merge --ff-only main
git push origin production
```

## Laravel Cloud — Production 환경 설정 (최초 1회)

1. **환경 생성**: 프로젝트에서 `New Environment` → 이름 `production` → 감시 브랜치를 `production`으로 지정
2. **데이터베이스**: Postgres 데이터베이스 생성 후 연결 (SQLite 금지 — 백업/동시성). 자동 백업 활성화
3. **배포 명령 (중요)** — staging과 다르게 설정:
   ```
   php artisan migrate --force
   ```
   ⚠️ 절대 `migrate:fresh` 를 쓰지 말 것 — 운영 DB가 전부 삭제됨.
   최초 배포 후 관리자 계정 생성은 1회만:
   ```
   php artisan db:seed --force
   ```
   (production + WORKFORCE_DEMO=false 에서는 관리자 계정 1개만 생성되고 데모 데이터는 생성되지 않음)
4. **환경 변수**:
   ```
   APP_ENV=production
   APP_DEBUG=false
   WORKFORCE_DEMO=false
   ADMIN_PASSWORD=<강력한 비밀번호>     # 미설정 시 관리자 계정은 Google 로그인 전용
   GEMINI_API_KEY=<키>                  # 베지 분석 + 음성 보고서
   GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET
   GOOGLE_REDIRECT_URI=https://<운영도메인>/auth/google/callback
   ```
5. **Google OAuth**: Google Cloud Console → 사용 중인 OAuth 클라이언트의
   Authorized redirect URIs에 운영 도메인 콜백 URL 추가
6. **도메인**: 운영 도메인 연결 (staging 도메인과 분리)

## 계정 · 로그인

- 관리자: `davidcho1973@gmail.com` (시더가 생성, Google 로그인 가능)
- 직원: 직원 등록 시 입력한 이메일과 동일한 Google 계정으로 로그인하면 자동 연결됨
- 데모 계정(mkim/cmartinez)은 production에서는 생성되지 않음

## 점검 체크리스트 (배포 후)

- [ ] 관리자 로그인 (Google 또는 ADMIN_PASSWORD)
- [ ] 현장 생성 + 위치/반경 설정 (GPS 검증)
- [ ] 직원 1명 등록 → 해당 계정으로 출근/퇴근
- [ ] 급여대장 내보내기 (엑셀 수식/서식 확인)
- [ ] `/export/timesheet` 일일 근태 엑셀
