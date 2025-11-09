# TG Slot Admin Panel အသုံးပြုမှု လမ်းညွှန်

ဒီစာရွက်လိပ်စာလမ်းညွှန်သည် TG Slot Project ၏ Admin Panel ကို Owner နှင့် Agent များအနေနှင့် အသုံးပြုရာတွင် လိုအပ်မည့် Feature များကို မြန်မာဘာသာဖြင့် တင်ပြထားပါသည်။

---

## ၁။ အခြေခံ သိရှိရန်

- **အသုံးပြုသူအမျိုးအစားများ**
  - `Owner` – စနစ်အားလုံးကိုထိန်းချုပ်သူ၊ Agent များအား စီမံခန့်ခွဲခြင်း။
  - `Agent` – Player များအား ဖန်တီး၊ ငွေလွှဲ စသည် စီမံခန့်ခွဲနိုင်သူ။
  - `Player` – Game ကစားသူ။
  - `SystemWallet` – ပရိုဂရမ်အတွင်း စုစုပေါင်းငွေလှည့်ပတ်မှုအတွက် အသုံးပြုသော system account။

- **WalletService**
  - Project တစ်ခုလုံး၏ balance update များသည် `App\Services\WalletService` တွင်ပါဝင်သော `deposit`, `withdraw`, `transfer` method များဖြင့် စီမံထားသည်။
  - Transfer Flow သတ်မှတ်ချက်သည် Owner → Agent → Player (နှင့် အပြန် Agent → Owner / Player → Agent) အတိအကျ ဖြစ်ရန်ရည်ရွယ်ထားသည်။

- **Admin Panel ဝင်ရန်**
  - URL – `http://<domain>/login`
  - Owner, Agent, SystemWallet တို့သာ Login ခွင့်ရှိသည်။ Login ပြီးသည်နှင့် Dashboard သို့ သွားမည်။

---

## ၂။ Dashboard (Owner/Agent)

Dashboard တွင် အောက်ပါအချက်အလက်များကို မြင်နိုင်သည် –

- စုစုပေါင်း Owner, Agent, Player အရေအတွက်
- Downline balance, Player balance စုစုပေါင်း
- Owner သို့မဟုတ် Agent ၏ လက်ရှိ balance
- ကတ်ထုတ်၊ ငွေဖြည့်, ငွေထုတ် Form (Owner တွင် Agent သို့ ငွေလွှဲရန်)
- ကာလအတွင်း လုပ်ဆောင်ခဲ့သော Transfer များအတွက် quick summary

---

## ၃။ Sidebar Feature များ

### ၃.၁ Player List

- **Owner View (`admin.players.grouped`)**
  - Agent တစ်ဦးစီအောက်ရှိ Player များကို Grouped Table ဖြင့် ကြည့်ရှုတတ်ရန် ဖြစ်သည်။
  - Owner သည် Player ဖန်တီး/ပြင်ဆင်/ပိတ်ပင်ခြင်း မလုပ်နိုင်ပါ (Read-Only)။
  - Agent တစ်ဦး၏ Player များကို လေ့လာလိုပါက `View Players` ခလုတ် ကာတွင် Agent Detail Page ဖွင့်နိုင်သည်။

- **Agent View (`admin.agent.players.index`)**
  - Player CRUD အသုံးပြုနိုင်သည် – Create, Edit, Delete, Ban/Unban, Logs, Report, Deposit, Withdraw, Change Password။
  - Player ဖန်တီးရာတွင် ကိုယ့် Agent balance မှ Player သို့ `WalletService::transfer` ဖြင့် ငွေလွှဲ၍ `TransferLog` မှတ်တမ်းတင်မည်။
  - Deposit/Withdraw ခလုတ်များသည် Agent ↔ Player ငွေလွှဲခြင်းကို အလိုအလျောက် သတ်မှတ်ထားသော TransactionName ဖြင့် လုပ်ဆောင်ပေးသည်။

### ၃.၂ Agent List (`admin.agent.*`)

- Owner သာလျှင် လက်ခံနိုင်သည်။ Agent အသစ်ဖန်တီးခြင်း၊ Agent ငွေလွှဲခြင်း၊ Profile/Report စီမံခြင်းတို့ ပါဝင်သည်။
- Cash In/Out Form သည် Owner ↔ Agent ငွေလွှဲရန်အသုံးပြုသည်။

### ၃.၃ Transfer Logs

- `TransferLogController@index` မှ အောက်ပါအချက်များကို မြင်နိုင်သည် –
  - Filtered Deposit/Withdraw/Profit
  - All-time Deposit/Withdraw/Profit
  - Date Range Filter, Transfer Type Filter
  - စာရင်းဇယားတွင် Owner ↔ Agent (အမှန်တကယ် transfer ဖြစ်သည့် များ) ပြသထားသည်။

- Detail View (`PlayertransferLog`) – သတ်မှတ်ထားသော Relationship အတွင်းရှိ User နှစ်ဦးကြား TransferLog များကိုကြည့်။

### ၃.၄ Contact, Bank, Reports

- လက္ခဏာကျသော Contact Form, Bank List, Game Lists, Promotions စသည့် Admin Function များအတွက် Sidebar တွင် လုပ်ဆောင်နိုင်သည်။

---

## ၄။ Wallet Integration (Seamless API)

G+ Provider Webhook များသည် အသစ်ရေးသားထားသော WalletService အပေါ်တွင် တစ်ရပ်တည်း ရပ်တည်ပါသည် –

1. **DepositController** – Provider ထံမှ WIN/SETTLED/PROMO စသော action များကို batch ဖြင့် ယူပြီး Player balance ထည့်သွင်းသည်။ Duplicate transaction မရှိစေရန် `place_bets.transaction_id` စစ်တစ်ပြုပြီး ရလဒ်တစ်ခုချင်းစီကို response မည်ဖြစ်သည်။  
2. **WithdrawController** – BET/WITHDRAW အတွက် Player balance လျော့ချပြီး Insufficient balance ဖြစ်ပါက appropriate error ပြန်ပို့သည်။  
3. **GetBalanceController** – Player balance ကို `users.balance` မှ တိုက်ရိုက်ထုတ်ယူပြီး သိမ်းဆည်းထားသော currency multiplier အတိုင်း ပြန်လည်ညှိနှိုင်းသည်။  
4. **PushBetDataController** – Provider ထံမှ Bet History ကို Push Bet Table ထဲသို့ upsert လုပ်နိုင်စေရန် ပြင်ဆင်ထားသည်။

`check_user_balance.php` CLI Script ကို အသုံးပြု၍ balance စာရင်းကို terminal မှ ကြည့်ရှုနိုင်သည်။

---

## ၅။ အသုံးပြုသူအလိုက် လုပ်ဆောင်မှု လမ်းညွှန် (မြန်မာဘာသာ)

### ၅.၁ Owner

1. **Login** – `http://<domain>/login` မှာ Owner credential ဖြင့် ဝင်ပါ။  
2. **Dashboard** – စုစုပေါင်း အချက်အလက်နှင့် Downline Balance များကို ကြည့်ရှုပါ။  
3. **Agent List** – Agent အသစ်ဖန်တီးခြင်း၊ Agent များအား Cash-in/Cash-out ပြုလုပ်ခြင်း၊ Ban/Unban, Password ပြန်လည်သတ်မှတ်ခြင်း စသည်တို့ကို လုပ်ဆောင်ပါ။  
4. **Player List** – Agent တစ်ဦးချင်းစီ၏ Player များကို Grouped Table ဖြင့် ကြည့်ပါ။ Owner သည် Player စီမံခန့်ခွဲမှု မလုပ်နိုင်ပါ (အချက်အလက်ကြည့်ရှုခွင့်သာရှိ)။  
5. **Transfer Logs** – အချိန်ပိုင်းခွဲခြားခြင်းနှင့် daily/all-time total များကို စစ်ဆေးပါ။ Owner ↔ Agent transfer များမှ profit/loss ကို စောင့်ကြည့်ပါ။  

### ၅.၂ Agent

1. **Login** – Agent credential ဖြင့် ဝင်ပါ။  
2. **Player List** –
   - `Create Player` ကို နှိပ်ပြီး Player Name, User Name, Password, Amount, Link စသည့် form ကို ဖြည့်ပါ။  
   - Amount ဖြည့်ပါက WalletService မှ Agent → Player transfer သွားမည်ဖြစ်ပြီး TransferLog မှတ်တမ်းတင်ထားသည်။  
3. **Player Action** – Deposit, Withdraw, Edit, Delete, Ban, Logs, Report, Change Password စသည် ဝန်ဆောင်မှုများ ကို ခလုတ်တစ်ခုချင်းစီမှ လုပ်ဆောင်နိုင်သည်။  
4. **Transfer Logs** – မိမိနှင့် ယခုပျော် Player အကြား သို့မဟုတ် မိမိ၏ သမီးဆုံး Player များအကြား TransferLog ကို စာရင်းဇယားအဖြစ် ကြည့်နိုင်သည်။  
5. **Reports** – Game Provider report များ၊ Promotion စာရင်းများကို သတ်မှတ်ထားသော Menu များမှ ကြည့်ရှုနိုင်သည်။  

### ၅.၃ System Wallet & API Integrator (Development)

- Provider webhook တက်လာသည့် transaction များသည် WalletService အပေါ်တွင် credit/debit ဖြစ်ကြသည်။ Testing ပြုလုပ်ပါက Postman သို့မဟုတ် provider sandbox မှ WIN/BET payload အမျိုးမျိုးကို ပြန်လည်ပို့၍ `transfer_logs` table နှင့် dashboard ပြန်လည်ဆန်းစစ်ပါ။  
- CLI မှ `php check_user_balance.php --user_name=PLAYER0001` ကဲ့သို့ အကောင့်တစ်ခုချင်းစာရင်းကို စစ်ဆေးနိုင်သည်။  

---

## ၆။ အရေးကြီး သတိပြုချက်များ

- `WalletService` မှာ Amount သတ်မှတ်ချက်ကို integer အနေနဲ့ အလုပ်လည်ပြီး zero သို့မဟုတ် အနုတ်ဂဏန်းများဖြင့် API လက်ခံပါက `InvalidArgumentException`/`RuntimeException` ပေါ်လာနိုင်သည်။  
- Transfers များသည် owner-to-agent-to-player သာခွင့်ပြုထားသဖြင့် hierarchy မညီမျှပါက `Transfers from X to Y are not permitted` ဆိုပြီး exception ပေါ်ပေါက်မည်။  
- TransferLog, PlaceBet table များသည် Provider request များကို ပြန်တွက်ရန်အတွက် အဓိကကြောင့်လည်းဖြစ်သည်။ Duplicate transaction များကို သတိပြုရန် `transaction_id` / `seamless_transaction_id` မတွေ့ရပါကသိရလိမ့်မည်။  
- `resources/logs` ထဲမှ `laravel.log` တွင် Provider request နှင့် အကြောင်းအရာ error များရေးသားထားပြီး debugging ပြုလုပ်နိုင်သည်။  

---

## ၇။ ဆောင်ရွက်ရန် Checklist

- [ ] Owner & Agent login credentials ကို စမ်းသပ်ပြီး Dashboard ပေါ်တွင် balance တိကျကြောင်းစစ်ပါ။  
- [ ] Agent အနေဖြင့် Player တစ်ဦးထည့်သွင်း၊ Amount ဖြည့်၍ transfer ပြုလုပ်ပါ။ TransferLog မှာ ပေါ်လာကြောင်းစစ်ပါ။  
- [ ] Agent သို့မဟုတ် Owner အနေဖြင့် Cash-in/Cash-out လုပ်ပြီး transfer flow နောက်ပြန်စစ်ပါ။  
- [ ] API – Deposit & Withdraw ဝင်လာသည့် ပြန်ထုတ်စာရင်းကို TransferLog တွင် ပြန်အတည်ပြုပါ။  
- [ ] GetBalance API တွင် Currency multiplier နှင့်ထိန်းချုပ်သည့် 1:1000 logic မှန်ကန်ကြောင်းသေချာစည်းပါ။  

---

TG Slot Admin Panel ကို အထူးအဆင်ပြေစွာ အသုံးချနိုင်ရန် အထက်ဖော်ပြပါ လမ်းညွှန်ကို အသုံးပြုပါ။ မည်သည့် Feature အပေါ်မေးခွန်းများရှိပါက Developers သို့မဟုတ် System Administrator ထံ အမြန်ဆုံးဆက်သွယ်ပါ။


