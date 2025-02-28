# WhatsApp Diet Planning System
## Technical Development Documentation

**Version:** 1.0  
**Date:** February 26, 2025  
**Author:** Claude AI  

---

## Table of Contents
1. [System Overview](#system-overview)
2. [Architecture](#architecture)
3. [Technology Stack](#technology-stack)
4. [Database Schema](#database-schema)
5. [API Endpoints](#api-endpoints)
6. [WhatsApp Integration](#whatsapp-integration)
7. [User Authentication & Authorization](#user-authentication--authorization)
8. [Payment Integration](#payment-integration)
9. [Development Workflow](#development-workflow)
10. [Deployment Strategy](#deployment-strategy)
11. [Testing Plan](#testing-plan)
12. [Security Considerations](#security-considerations)
13. [Scalability Planning](#scalability-planning)
14. [Implementation Timeline](#implementation-timeline)

---

## System Overview

The WhatsApp Diet Planning System is a comprehensive solution that allows gym owners to provide personalized nutrition guidance to their clients through WhatsApp. The system features automated assessments, personalized meal planning, progress tracking, and plan adjustments based on client progress.

### Core Features

1. **Multi-tier User Management**
   - Admin (Platform Owner)
   - Gym Admin (Gym Owners)
   - Trainers (Gym Staff)
   - Dietitians (Nutrition Experts)
   - End Users (Gym Clients)

2. **WhatsApp-based Client Interaction**
   - Automated assessment questionnaires
   - Personalized meal plan delivery
   - Progress tracking through messaging
   - Reminder and follow-up system

3. **Personalized Diet Planning**
   - Assessment-based plan creation
   - Automated adjustments based on progress
   - Specialized health protocols
   - Recipe and shopping list generation

4. **Admin Dashboard**
   - Client management
   - Plan oversight and modification
   - Analytics and reporting
   - Subscription management

5. **Payment Processing**
   - Stripe integration
   - Razorpay integration
   - Subscription management
   - Payment reporting

---

## Architecture

The system follows a modern three-tier architecture with clear separation of concerns:

### High-Level Architecture Diagram

```
┌───────────────────────────────────────────────────────────────┐
│                      CLIENT LAYER                              │
│                                                               │
│  ┌─────────────────┐  ┌──────────────────┐  ┌──────────────┐  │
│  │  React Admin    │  │  WhatsApp Client │  │  Web Portal  │  │
│  │  Dashboard      │  │  Interface       │  │  (Users)     │  │
│  └─────────────────┘  └──────────────────┘  └──────────────┘  │
└───────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌───────────────────────────────────────────────────────────────┐
│                      API LAYER                                 │
│                                                               │
│  ┌────────────────┐ ┌────────────────┐ ┌────────────────────┐ │
│  │ Authentication │ │ WhatsApp API   │ │ Business Logic     │ │
│  │ & Authorization│ │ Controller     │ │ Controllers        │ │
│  └────────────────┘ └────────────────┘ └────────────────────┘ │
│                                                               │
│  ┌────────────────┐ ┌────────────────┐ ┌────────────────────┐ │
│  │ Diet Planning  │ │ Payment        │ │ Analytics          │ │
│  │ Service        │ │ Controller     │ │ Service            │ │
│  └────────────────┘ └────────────────┘ └────────────────────┘ │
└───────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌───────────────────────────────────────────────────────────────┐
│                     DATA LAYER                                 │
│                                                               │
│  ┌─────────────────┐  ┌──────────────────┐  ┌──────────────┐  │
│  │  MySQL Database │  │  Redis Cache     │  │  File Storage│  │
│  │  (Main Data)    │  │  (Session/Cache) │  │  (Media)     │  │
│  └─────────────────┘  └──────────────────┘  └──────────────┘  │
└───────────────────────────────────────────────────────────────┘
```

### Component Interaction

1. **Frontend to Backend Communication**
   - RESTful API for dashboard operations
   - WebSockets for real-time updates (optional)
   - Secure authentication using Laravel Sanctum

2. **WhatsApp Integration**
   - Webhook endpoints for incoming messages
   - Outgoing message processing queue
   - Message template management

3. **Backend to Database**
   - Eloquent ORM for database interactions
   - Redis for caching and queue management
   - Transaction management for payment processing

---

## Technology Stack

### Frontend
- **Framework:** React 18+
- **State Management:** Redux Toolkit / React Query
- **UI Components:** Material UI
- **Form Handling:** React Hook Form
- **API Client:** Axios
- **Charts/Visualization:** Recharts
- **Build Tool:** Vite

### Backend
- **Framework:** Laravel 10+
- **PHP Version:** 8.2+
- **API:** RESTful with Laravel Resources
- **Authentication:** Laravel Sanctum
- **Background Jobs:** Laravel Queues

### Database
- **Primary DB:** MySQL 8.0+
- **Caching:** Redis
- **File Storage:** S3-compatible storage

### WhatsApp Integration
- **API:** WhatsApp Business API
- **Client Library:** Meta's official PHP SDK
- **Webhooks:** Laravel-based webhook handlers
- **Message Queue:** Redis + Laravel Queue

### DevOps
- **Containerization:** Docker
- **Version Control:** Git
- **CI/CD:** GitHub Actions (optional)
- **Monitoring:** Laravel Telescope for development

### Payment Processing
- **Gateways:**
  - Stripe
  - Razorpay
- **Libraries:**
  - stripe/stripe-php
  - razorpay/razorpay

---

## Database Schema

### Core Tables

#### Users
```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'gym_admin', 'trainer', 'dietitian', 'client') NOT NULL,
    phone VARCHAR(20) UNIQUE,
    whatsapp_phone VARCHAR(20) UNIQUE,
    whatsapp_id VARCHAR(255) UNIQUE,
    status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
    email_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Gyms
```sql
CREATE TABLE gyms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(255),
    owner_id BIGINT UNSIGNED NOT NULL,
    subscription_status ENUM('active', 'inactive', 'trial') DEFAULT 'trial',
    subscription_expires_at TIMESTAMP NULL,
    max_clients INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id)
);
```

#### Gym_User (Relationships)
```sql
CREATE TABLE gym_user (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('gym_admin', 'trainer', 'dietitian', 'client') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (gym_id) REFERENCES gyms(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

#### Client_Profiles
```sql
CREATE TABLE client_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    age INT,
    gender ENUM('male', 'female', 'other'),
    height DECIMAL(5,2),
    current_weight DECIMAL(5,2),
    target_weight DECIMAL(5,2),
    activity_level ENUM('sedentary', 'lightly_active', 'moderately_active', 'very_active', 'extremely_active'),
    diet_type ENUM('omnivore', 'vegetarian', 'vegan', 'pescatarian', 'flexitarian', 'keto', 'paleo', 'other'),
    health_conditions JSON,
    allergies JSON,
    recovery_needs JSON,
    meal_preferences JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

#### Diet_Plans
```sql
CREATE TABLE diet_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    daily_calories INT,
    protein_grams INT,
    carbs_grams INT,
    fats_grams INT,
    status ENUM('active', 'inactive', 'completed') DEFAULT 'active',
    start_date DATE,
    end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

#### Meal_Plans
```sql
CREATE TABLE meal_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    diet_plan_id BIGINT UNSIGNED NOT NULL,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (diet_plan_id) REFERENCES diet_plans(id)
);
```

#### Meals
```sql
CREATE TABLE meals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meal_plan_id BIGINT UNSIGNED NOT NULL,
    meal_type ENUM('breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner', 'evening_snack', 'pre_workout', 'post_workout'),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    calories INT,
    protein_grams INT,
    carbs_grams INT,
    fats_grams INT,
    time_of_day TIME,
    recipes JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meal_plan_id) REFERENCES meal_plans(id)
);
```

#### Progress_Tracking
```sql
CREATE TABLE progress_tracking (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    tracking_date DATE NOT NULL,
    weight DECIMAL(5,2),
    measurements JSON,
    energy_level INT,
    meal_compliance INT,
    water_intake INT,
    exercise_compliance INT,
    notes TEXT,
    photos JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id)
);
```

#### WhatsApp_Conversations
```sql
CREATE TABLE whatsapp_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    wa_message_id VARCHAR(255),
    direction ENUM('incoming', 'outgoing'),
    message_type ENUM('text', 'image', 'template', 'interactive', 'location'),
    content TEXT,
    metadata JSON,
    status ENUM('sent', 'delivered', 'read', 'failed'),
    sent_at TIMESTAMP,
    delivered_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

#### Assessment_Sessions
```sql
CREATE TABLE assessment_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    current_phase INT DEFAULT 1,
    current_question VARCHAR(255),
    responses JSON,
    status ENUM('in_progress', 'completed', 'abandoned'),
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id)
);
```

#### Subscriptions
```sql
CREATE TABLE subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id BIGINT UNSIGNED NOT NULL,
    plan_type ENUM('starter', 'growth', 'enterprise'),
    status ENUM('active', 'inactive', 'trial', 'cancelled'),
    price DECIMAL(10,2),
    billing_cycle ENUM('monthly', 'quarterly', 'annually'),
    started_at TIMESTAMP,
    ends_at TIMESTAMP,
    trial_ends_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (gym_id) REFERENCES gyms(id)
);
```

#### Payments
```sql
CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id BIGINT UNSIGNED NOT NULL,
    gym_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'INR',
    payment_method ENUM('stripe', 'razorpay', 'manual'),
    payment_id VARCHAR(255),
    status ENUM('pending', 'completed', 'failed', 'refunded'),
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id),
    FOREIGN KEY (gym_id) REFERENCES gyms(id)
);
```

### Additional Relationships

#### Health_Protocols
```sql
CREATE TABLE health_protocols (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    protocol_type ENUM('weight_loss', 'muscle_gain', 'liver_detox', 'lung_recovery', 'hair_health', 'sleep', 'custom'),
    rules JSON,
    recommendations JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Client_Protocols
```sql
CREATE TABLE client_protocols (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    protocol_id BIGINT UNSIGNED NOT NULL,
    status ENUM('active', 'completed', 'paused'),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id),
    FOREIGN KEY (protocol_id) REFERENCES health_protocols(id)
);
```

---

## API Endpoints

### Authentication Endpoints

```
POST   /api/v1/auth/register             - Register new user
POST   /api/v1/auth/login                - Login user
POST   /api/v1/auth/logout               - Logout user
POST   /api/v1/auth/refresh              - Refresh token
GET    /api/v1/auth/me                   - Get logged in user info
POST   /api/v1/auth/password/email       - Send password reset email
POST   /api/v1/auth/password/reset       - Reset password
POST   /api/v1/auth/email/verify         - Verify email
```

### User Management Endpoints

```
GET    /api/v1/users                     - List users (with filters)
POST   /api/v1/users                     - Create user
GET    /api/v1/users/{id}                - Get user details
PUT    /api/v1/users/{id}                - Update user
DELETE /api/v1/users/{id}                - Delete user
GET    /api/v1/users/{id}/gyms           - Get user's gyms
GET    /api/v1/users/{id}/clients        - Get trainer's clients
```

### Gym Management Endpoints

```
GET    /api/v1/gyms                      - List gyms
POST   /api/v1/gyms                      - Create gym
GET    /api/v1/gyms/{id}                 - Get gym details
PUT    /api/v1/gyms/{id}                 - Update gym
DELETE /api/v1/gyms/{id}                 - Delete gym
GET    /api/v1/gyms/{id}/users           - Get gym users
POST   /api/v1/gyms/{id}/users           - Add user to gym
DELETE /api/v1/gyms/{id}/users/{userId}  - Remove user from gym
GET    /api/v1/gyms/{id}/subscription    - Get gym subscription
```

### Diet Plan Endpoints

```
GET    /api/v1/diet-plans                - List diet plans
POST   /api/v1/diet-plans                - Create diet plan
GET    /api/v1/diet-plans/{id}           - Get diet plan details
PUT    /api/v1/diet-plans/{id}           - Update diet plan
DELETE /api/v1/diet-plans/{id}           - Delete diet plan
GET    /api/v1/diet-plans/{id}/meals     - Get diet plan meals
POST   /api/v1/diet-plans/{id}/duplicate - Duplicate diet plan
```

### Client Management Endpoints

```
GET    /api/v1/clients                   - List clients
GET    /api/v1/clients/{id}              - Get client details
GET    /api/v1/clients/{id}/profile      - Get client profile
PUT    /api/v1/clients/{id}/profile      - Update client profile
GET    /api/v1/clients/{id}/diet-plans   - Get client diet plans
GET    /api/v1/clients/{id}/progress     - Get client progress
POST   /api/v1/clients/{id}/progress     - Add progress entry
```

### WhatsApp Integration Endpoints

```
POST   /api/v1/whatsapp/webhook          - WhatsApp webhook receiver
POST   /api/v1/whatsapp/send             - Send WhatsApp message
POST   /api/v1/whatsapp/template         - Send template message
GET    /api/v1/whatsapp/templates        - List available templates
POST   /api/v1/whatsapp/templates        - Create template
GET    /api/v1/whatsapp/conversations    - Get conversations
```

### Assessment Endpoints

```
POST   /api/v1/assessments/start         - Start new assessment
GET    /api/v1/assessments/{id}          - Get assessment status
PUT    /api/v1/assessments/{id}          - Update assessment
POST   /api/v1/assessments/{id}/complete - Complete assessment
GET    /api/v1/assessments/{id}/result   - Get assessment result
```

### Payment Endpoints

```
GET    /api/v1/subscriptions             - List subscriptions
POST   /api/v1/subscriptions             - Create subscription
GET    /api/v1/subscriptions/{id}        - Get subscription details
PUT    /api/v1/subscriptions/{id}        - Update subscription
POST   /api/v1/payments/stripe/webhook   - Stripe webhook
POST   /api/v1/payments/razorpay/webhook - Razorpay webhook
GET    /api/v1/payments                  - List payments
```

### Analytics Endpoints

```
GET    /api/v1/analytics/overview        - Get system overview
GET    /api/v1/analytics/gym/{id}        - Get gym analytics
GET    /api/v1/analytics/clients         - Get client analytics
GET    /api/v1/analytics/usage           - Get system usage stats
GET    /api/v1/analytics/revenue         - Get revenue analytics
```

---

## WhatsApp Integration

The system will integrate with WhatsApp Business API following Meta's latest guidelines to ensure compliance and optimal performance.

### WhatsApp Business API Setup

1. **Prerequisites**
   - Facebook Business Account
   - WhatsApp Business Account
   - Meta Developer Account
   - Business Verification

2. **Integration Steps**
   - Register application in Meta Developer Dashboard
   - Configure WhatsApp Business API
   - Set up webhooks
   - Create message templates
   - Implement client-side handling

### WhatsApp Messaging Flow

```
┌─────────────────┐      ┌──────────────────┐      ┌─────────────────┐
│   Client        │      │   WhatsApp       │      │   Our System    │
│   WhatsApp      │◄────►│   Business API   │◄────►│   Backend       │
└─────────────────┘      └──────────────────┘      └─────────────────┘
        │                                                    ▲
        │                                                    │
        │              ┌────────────────────────────┐       │
        └─────────────►│  Webhook Endpoint          │───────┘
                       │  (Receives messages)       │
                       └────────────────────────────┘
```

### Message Types

1. **Incoming Messages**
   - Text messages
   - Button responses
   - List selections
   - Media (images)

2. **Outgoing Messages**
   - Text messages
   - Template messages
   - Interactive messages (with buttons/lists)
   - Media messages

### Conversation Workflow

1. **Assessment Sequence**
   - Predefined question flow
   - Branching logic based on responses
   - Response validation
   - Progress tracking

2. **Plan Delivery**
   - Structured meal plan messages
   - Recipe sharing
   - Shopping list generation
   - Visual guidance (images)

3. **Progress Tracking**
   - Daily check-ins
   - Weekly assessments
   - Photo sharing for progress
   - Interactive feedback

### Message Templates

You'll need to create and register message templates for:

1. **Onboarding**
   - Welcome message
   - Service explanation
   - Privacy policy
   - Consent collection

2. **Assessment**
   - Phase introductions
   - Follow-up questions
   - Completion notifications

3. **Plan Delivery**
   - Daily meal structure
   - Recipe instructions
   - Nutrition information

4. **Follow-ups**
   - Daily check-ins
   - Progress tracking
   - Plan adjustment notifications

### WhatsApp Business API Implementation

```php
// Example code for sending a WhatsApp message

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $apiUrl;
    protected $token;
    
    public function __construct()
    {
        $this->apiUrl = config('services.whatsapp.api_url');
        $this->token = config('services.whatsapp.token');
    }
    
    public function sendTextMessage(User $user, string $message)
    {
        if (!$user->whatsapp_phone) {
            Log::error("User doesn't have WhatsApp phone number", ['user_id' => $user->id]);
            return false;
        }
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $user->whatsapp_phone,
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ];
        
        try {
            $response = Http::withToken($this->token)
                ->post("{$this->apiUrl}/messages", $payload);
                
            if ($response->successful()) {
                // Log successful message
                return $response->json();
            } else {
                Log::error("WhatsApp message failed", [
                    'user_id' => $user->id,
                    'error' => $response->body()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error("WhatsApp API exception", [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    public function sendTemplateMessage(User $user, string $template, array $parameters = [])
    {
        // Implementation for template messages
    }
    
    public function sendInteractiveMessage(User $user, string $content, array $options)
    {
        // Implementation for interactive messages
    }
    
    public function handleIncomingMessage(array $payload)
    {
        // Implementation for handling webhook data
    }
}
```

### Razorpay Integration

Razorpay provides an alternative payment gateway tailored for the Indian market, with support for UPI, netbanking, and other local payment methods.

#### Configuration

```php
// config/services.php
return [
    // Other services...
    
    'razorpay' => [
        'key' => env('RAZORPAY_KEY'),
        'secret' => env('RAZORPAY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'plans' => [
            'starter' => [
                'monthly' => env('RAZORPAY_PLAN_STARTER_MONTHLY'),
                'quarterly' => env('RAZORPAY_PLAN_STARTER_QUARTERLY'),
                'annually' => env('RAZORPAY_PLAN_STARTER_ANNUALLY'),
            ],
            'growth' => [
                'monthly' => env('RAZORPAY_PLAN_GROWTH_MONTHLY'),
                'quarterly' => env('RAZORPAY_PLAN_GROWTH_QUARTERLY'),
                'annually' => env('RAZORPAY_PLAN_GROWTH_ANNUALLY'),
            ],
            'enterprise' => [
                'monthly' => env('RAZORPAY_PLAN_ENTERPRISE_MONTHLY'),
                'quarterly' => env('RAZORPAY_PLAN_ENTERPRISE_QUARTERLY'),
                'annually' => env('RAZORPAY_PLAN_ENTERPRISE_ANNUALLY'),
            ],
        ],
    ],
];
```

#### Service Implementation

```php
namespace App\Services;

use App\Models\Gym;
use App\Models\Subscription;
use App\Models\Payment;
use Razorpay\Api\Api;
use Illuminate\Support\Facades\Log;

class RazorpayService
{
    protected $razorpay;
    
    public function __construct()
    {
        $this->razorpay = new Api(
            config('services.razorpay.key'),
            config('services.razorpay.secret')
        );
    }
    
    public function createSubscription(Gym $gym, string $planType, string $billingCycle)
    {
        $planId = $this->getPlanId($planType, $billingCycle);
        $owner = $gym->owner;
        
        try {
            // Create customer if not exists
            $customer = $this->getOrCreateCustomer($owner);
            
            // Create subscription
            $subscriptionData = [
                'plan_id' => $planId,
                'customer_id' => $customer['id'],
                'total_count' => $this->getTotalCount($billingCycle),
                'notes' => [
                    'gym_id' => $gym->id,
                    'plan_type' => $planType,
                    'billing_cycle' => $billingCycle,
                ]
            ];
            
            $razorpaySubscription = $this->razorpay->subscription->create($subscriptionData);
            
            // Store subscription in database
            $subscription = new Subscription();
            $subscription->gym_id = $gym->id;
            $subscription->plan_type = $planType;
            $subscription->billing_cycle = $billingCycle;
            $subscription->status = 'active';
            $subscription->razorpay_id = $razorpaySubscription->id;
            $subscription->started_at = now();
            $subscription->ends_at = $this->calculateEndDate($billingCycle);
            $subscription->save();
            
            return [
                'subscription' => $subscription,
                'razorpay_subscription' => $razorpaySubscription
            ];
        } catch (\Exception $e) {
            Log::error('Razorpay subscription creation failed', [
                'gym_id' => $gym->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    protected function getOrCreateCustomer($user)
    {
        if ($user->razorpay_customer_id) {
            return $this->razorpay->customer->fetch($user->razorpay_customer_id);
        }
        
        $customer = $this->razorpay->customer->create([
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->phone,
            'notes' => [
                'user_id' => $user->id
            ]
        ]);
        
        $user->razorpay_customer_id = $customer->id;
        $user->save();
        
        return $customer;
    }
    
    protected function getPlanId(string $planType, string $billingCycle)
    {
        $plans = config('services.razorpay.plans');
        
        return $plans[$planType][$billingCycle] ?? 
            throw new \Exception('Invalid plan type or billing cycle');
    }
    
    protected function getTotalCount(string $billingCycle)
    {
        return match($billingCycle) {
            'monthly' => 12, // 1 year worth of monthly payments
            'quarterly' => 4, // 1 year worth of quarterly payments
            'annually' => 1, // 1 annual payment
            default => 12,
        };
    }
    
    protected function calculateEndDate(string $billingCycle)
    {
        $now = now();
        
        return match($billingCycle) {
            'monthly' => $now->addMonth(),
            'quarterly' => $now->addMonths(3),
            'annually' => $now->addYear(),
            default => $now->addMonth(),
        };
    }
    
    public function verifyPayment(string $paymentId, string $orderId, string $signature)
    {
        try {
            $attributes = [
                'razorpay_payment_id' => $paymentId,
                'razorpay_order_id' => $orderId,
                'razorpay_signature' => $signature
            ];
            
            $this->razorpay->utility->verifyPaymentSignature($attributes);
            return true;
        } catch (\Exception $e) {
            Log::error('Razorpay payment verification failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
```

#### Webhook Handling

```php
namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RazorpayWebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $payload = $request->all();
        
        // Verify webhook signature
        $signature = $request->header('X-Razorpay-Signature');
        if (!$this->verifySignature($payload, $signature)) {
            return response('Invalid signature', 400);
        }
        
        $eventType = $payload['event'];
        
        switch($eventType) {
            case 'subscription.authenticated':
                $this->handleSubscriptionAuthenticated($payload);
                break;
                
            case 'subscription.activated':
                $this->handleSubscriptionActivated($payload);
                break;
                
            case 'subscription.charged':
                $this->handleSubscriptionCharged($payload);
                break;
                
            case 'subscription.cancelled':
                $this->handleSubscriptionCancelled($payload);
                break;
                
            default:
                Log::info('Unhandled Razorpay webhook', [
                    'event' => $eventType
                ]);
        }
        
        return response('Webhook processed', 200);
    }
    
    protected function verifySignature($payload, $signature)
    {
        $webhookSecret = config('services.razorpay.webhook_secret');
        $expectedSignature = hash_hmac('sha256', json_encode($payload), $webhookSecret);
        
        return hash_equals($expectedSignature, $signature);
    }
    
    protected function handleSubscriptionAuthenticated($payload)
    {
        $subscriptionId = $payload['payload']['subscription']['entity']['id'];
        $notes = $payload['payload']['subscription']['entity']['notes'];
        
        if (empty($notes['gym_id'])) {
            Log::warning('Razorpay subscription without gym_id', ['payload' => $payload]);
            return;
        }
        
        $gymId = $notes['gym_id'];
        $subscription = Subscription::where('gym_id', $gymId)
            ->where('razorpay_id', $subscriptionId)
            ->first();
        
        if ($subscription) {
            $subscription->status = 'active';
            $subscription->save();
            
            Log::info('Subscription authenticated', [
                'gym_id' => $gymId,
                'subscription_id' => $subscription->id
            ]);
        }
    }
    
    protected function handleSubscriptionCharged($payload)
    {
        $subscriptionId = $payload['payload']['subscription']['entity']['id'];
        $paymentId = $payload['payload']['payment']['entity']['id'];
        $amount = $payload['payload']['payment']['entity']['amount'] / 100; // Convert from paise
        
        $subscription = Subscription::where('razorpay_id', $subscriptionId)->first();
        
        if (!$subscription) {
            Log::warning('Payment received for unknown subscription', [
                'razorpay_subscription_id' => $subscriptionId
            ]);
            return;
        }
        
        $payment = new Payment();
        $payment->subscription_id = $subscription->id;
        $payment->gym_id = $subscription->gym_id;
        $payment->amount = $amount;
        $payment->currency = 'INR';
        $payment->payment_method = 'razorpay';
        $payment->payment_id = $paymentId;
        $payment->status = 'completed';
        $payment->metadata = json_encode($payload['payload']['payment']['entity']);
        $payment->save();
        
        Log::info('Payment recorded', [
            'gym_id' => $subscription->gym_id,
            'payment_id' => $payment->id,
            'amount' => $payment->amount
        ]);
    }
    
    protected function handleSubscriptionCancelled($payload)
    {
        $subscriptionId = $payload['payload']['subscription']['entity']['id'];
        
        $subscription = Subscription::where('razorpay_id', $subscriptionId)->first();
        
        if ($subscription) {
            $subscription->status = 'cancelled';
            $subscription->save();
            
            Log::info('Subscription cancelled', [
                'gym_id' => $subscription->gym_id,
                'subscription_id' => $subscription->id
            ]);
        }
    }
}
```

---

## User Authentication & Authorization

### Authentication Strategy

The system uses Laravel Sanctum for API authentication, providing token-based authentication for the SPA frontend and API access.

```php
// Example LoginController method
public function login(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (!Auth::attempt($request->only('email', 'password'))) {
        return response()->json([
            'message' => 'Invalid login details'
        ], 401);
    }

    $user = User::where('email', $request->email)->firstOrFail();
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'access_token' => $token,
        'token_type' => 'Bearer',
        'user' => $user->load('roles')
    ]);
}
```

### Role-Based Permission System

The system implements a robust role-based permission system using spatie/laravel-permission.

#### Roles

1. **System Admin**
   - Full system access
   - Manage gyms
   - Manage all users
   - Access all analytics

2. **Gym Admin**
   - Manage their gym
   - Manage gym users
   - View gym analytics
   - Manage subscriptions

3. **Trainer**
   - View assigned clients
   - View client progress
   - Provide feedback
   - Limited plan modifications

4. **Dietitian**
   - Create and modify diet plans
   - View client progress
   - Provide nutritional feedback
   - Manage meal templates

5. **Client**
   - View own diet plans
   - Submit progress updates
   - Access educational content
   - Manage own profile

#### Permission Configuration

```php
// Example permissions setup

// Define permissions
$permissions = [
    // User management
    'view_users', 'create_users', 'edit_users', 'delete_users',
    
    // Gym management
    'view_gyms', 'create_gyms', 'edit_gyms', 'delete_gyms',
    
    // Client management
    'view_clients', 'create_clients', 'edit_clients', 'delete_clients',
    
    // Diet plan management
    'view_diet_plans', 'create_diet_plans', 'edit_diet_plans', 'delete_diet_plans',
    
    // Progress tracking
    'view_progress', 'create_progress', 'edit_progress',
    
    // Subscription management
    'view_subscriptions', 'create_subscriptions', 'edit_subscriptions',
    
    // Analytics
    'view_analytics', 'view_financial_data',
    
    // WhatsApp messaging
    'send_whatsapp_messages', 'view_conversations',
];

// Assign permissions to roles
$roles = [
    'admin' => $permissions,
    
    'gym_admin' => [
        'view_users', 'create_users', 'edit_users',
        'view_clients', 'create_clients', 'edit_clients',
        'view_diet_plans', 'create_diet_plans', 'edit_diet_plans',
        'view_progress', 'view_analytics',
        'view_subscriptions', 'edit_subscriptions',
        'send_whatsapp_messages', 'view_conversations',
    ],
    
    'trainer' => [
        'view_clients',
        'view_diet_plans',
        'view_progress', 'create_progress',
        'send_whatsapp_messages', 'view_conversations',
    ],
    
    'dietitian' => [
        'view_clients',
        'view_diet_plans', 'create_diet_plans', 'edit_diet_plans',
        'view_progress', 'create_progress',
        'send_whatsapp_messages', 'view_conversations',
    ],
    
    'client' => [
        'view_diet_plans',
        'create_progress',
    ],
];
```

---

## Payment Integration

The system integrates with both Stripe and Razorpay to facilitate subscription payments and one-time charges.

### Stripe Integration

The system uses Stripe for processing gym owner subscriptions and handling recurring payments.

#### Configuration

```php
// config/services.php
return [
    // Other services...
    
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'prices' => [
            'starter' => [
                'monthly' => env('STRIPE_PRICE_STARTER_MONTHLY'),
                'quarterly' => env('STRIPE_PRICE_STARTER_QUARTERLY'),
                'annually' => env('STRIPE_PRICE_STARTER_ANNUALLY'),
            ],
            'growth' => [
                'monthly' => env('STRIPE_PRICE_GROWTH_MONTHLY'),
                'quarterly' => env('STRIPE_PRICE_GROWTH_QUARTERLY'),
                'annually' => env('STRIPE_PRICE_GROWTH_ANNUALLY'),
            ],
            'enterprise' => [
                'monthly' => env('STRIPE_PRICE_ENTERPRISE_MONTHLY'),
                'quarterly' => env('STRIPE_PRICE_ENTERPRISE_QUARTERLY'),
                'annually' => env('STRIPE_PRICE_ENTERPRISE_ANNUALLY'),
            ],
        ],
    ],
];

```php
// Example Stripe subscription creation

namespace App\Services;

use App\Models\Gym;
use App\Models\Subscription;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;

class StripeService
{
    protected $stripe;
    
    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }
    
    public function createSubscription(Gym $gym, string $planType, string $billingCycle)
    {
        // Get price ID based on plan type and billing cycle
        $priceId = $this->getPriceId($planType, $billingCycle);
        
        try {
            // Create or get customer
            $customer = $this->getOrCreateCustomer($gym);
            
            // Create subscription
            $stripeSubscription = $this->stripe->subscriptions->create([
                'customer' => $customer->id,
                'items' => [
                    ['price' => $priceId],
                ],
                'metadata' => [
                    'gym_id' => $gym->id,
                    'plan_type' => $planType,
                    'billing_cycle' => $billingCycle,
                ],
            ]);
            
            // Store subscription in database
            $subscription = new Subscription();
            $subscription->gym_id = $gym->id;
            $subscription->plan_type = $planType;
            $subscription->billing_cycle = $billingCycle;
            $subscription->status = 'active';
            $subscription->started_at = now();
            $subscription->ends_at = $this->calculateEndDate($billingCycle);
            $subscription->save();
            
            return $subscription;
        } catch (\Exception $e) {
            Log::error('Stripe subscription creation failed', [
                'gym_id' => $gym->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    protected function getOrCreateCustomer(Gym $gym)
    {
        $owner = $gym->owner;
        
        if ($owner->stripe_customer_id) {
            return $this->stripe->customers->retrieve($owner->stripe_customer_id);
        }
        
        $customer = $this->stripe->customers->create([
            'email' => $owner->email,
            'name' => $owner->name,
            'phone' => $owner->phone,
            'metadata' => [
                'gym_id' => $gym->id,
                'user_id' => $owner->id
            ]
        ]);
        
        $owner->stripe_customer_id = $customer->id;
        $owner->save();
        
        return $customer;
    }
    
    protected function getPriceId(string $planType, string $billingCycle)
    {
        $prices = [
            'starter' => [
                'monthly' => config('services.stripe.prices.starter.monthly'),
                'quarterly' => config('services.stripe.prices.starter.quarterly'),
                'annually' => config('services.stripe.prices.starter.annually'),
            ],
            'growth' => [
                'monthly' => config('services.stripe.prices.growth.monthly'),
                'quarterly' => config('services.stripe.prices.growth.quarterly'),
                'annually' => config('services.stripe.prices.growth.annually'),
            ],
            'enterprise' => [
                'monthly' => config('services.stripe.prices.enterprise.monthly'),
                'quarterly' => config('services.stripe.prices.enterprise.quarterly'),
                'annually' => config('services.stripe.prices.enterprise.annually'),
            ],
        ];
        
        return $prices[$planType][$billingCycle] ?? throw new \Exception('Invalid plan type or billing cycle');
    }
    
    protected function calculateEndDate(string $billingCycle)
    {
        $now = now();
        
        return match($billingCycle) {
            'monthly' => $now->addMonth(),
            'quarterly' => $now->addMonths(3),
            'annually' => $now->addYear(),
            default => $now->addMonth(),
        };
    }
}
```

#### Webhook Handling

```php
// StripeWebhookController.php
namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;

class StripeWebhookController extends CashierController
{
    protected function handleCustomerSubscriptionCreated(array $payload)
    {
        $subscriptionId = $payload['data']['object']['id'];
        $customerId = $payload['data']['object']['customer'];
        $metadata = $payload['data']['object']['metadata'];
        
        if (empty($metadata['gym_id'])) {
            Log::warning('Stripe subscription created without gym_id', ['payload' => $payload]);
            return;
        }
        
        $gymId = $metadata['gym_id'];
        $subscription = Subscription::where('gym_id', $gymId)
            ->where('status', 'active')
            ->first();
        
        if ($subscription) {
            $subscription->stripe_id = $subscriptionId;
            $subscription->save();
            
            Log::info('Subscription updated with Stripe ID', [
                'gym_id' => $gymId,
                'subscription_id' => $subscription->id,
                'stripe_id' => $subscriptionId
            ]);
        }
    }
    
    protected function handleInvoicePaymentSucceeded(array $payload)
    {
        $invoice = $payload['data']['object'];
        $customerId = $invoice['customer'];
        $subscriptionId = $invoice['subscription'];
        
        if (!$subscriptionId) {
            return;
        }
        
        $subscription = Subscription::where('stripe_id', $subscriptionId)->first();
        
        if (!$subscription) {
            Log::warning('Payment received for unknown subscription', [
                'stripe_subscription_id' => $subscriptionId
            ]);
            return;
        }
        
        $payment = new Payment();
        $payment->subscription_id = $subscription->id;
        $payment->gym_id = $subscription->gym_id;
        $payment->amount = $invoice['amount_paid'] / 100; // Convert from cents
        $payment->currency = $invoice['currency'];
        $payment->payment_method = 'stripe';
        $payment->payment_id = $invoice['id'];
        $payment->status = 'completed';
        $payment->metadata = json_encode($invoice);
        $payment->save();
        
        Log::info('Payment recorded', [
            'gym_id' => $subscription->gym_id,
            'payment_id' => $payment->id,
            'amount' => $payment->amount
        ]);
    }
}
```