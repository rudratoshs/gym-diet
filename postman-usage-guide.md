# WhatsApp Diet System - Postman Testing Guide

## Setup Instructions

### 1. Import Collection and Environment

1. Download both files:
   - `WhatsApp Diet System API.postman_collection.json`
   - `WhatsApp Diet System.postman_environment.json`

2. In Postman, click on **Import** and load both files

3. Make sure to select the **WhatsApp Diet System** environment from the dropdown in the top-right corner

### 2. Configure Environment Variables

1. Ensure your Laravel server is running (default: `http://localhost:8000`)
2. If your server is running on a different URL, update the `base_url` environment variable

### 3. Authentication Flow

The collection is set up to automatically save authentication tokens to environment variables:

1. Run the **Login** request with admin credentials
   - This will automatically set the `auth_token` variable

2. Run the **Login (Client)** request
   - This will set the `client_token` variable

3. Run the **Login (Trainer)** request
   - This will set the `trainer_token` variable

4. You can now test requests with different user roles using the appropriate token

## Testing Strategy

### 1. Core Authentication

Test the authentication flow:
1. Register a new user
2. Login with the new user
3. Get current user details
4. Logout

### 2. User Management

1. List users (admin token)
2. Create a new user (admin token)
3. Update user (admin token)
4. Try unauthorized operations (client token)

### 3. Gym Management

1. Create a new gym (admin token)
2. List gyms (admin token)
3. Add users to a gym
4. Try unauthorized operations (client token)

### 4. Client Profiles

1. Create a client profile (admin or trainer token)
2. Get client profile (multiple roles)
3. Update client profile (multiple roles)

### 5. Diet Plans

Follow this sequence for thorough testing:

1. Create a diet plan for a client
2. Get the diet plan details
3. Create meal plans for each day of the week
4. Add meals to each meal plan
5. Test getting all meal plans for a diet plan
6. Test meal updates and deletions
7. Test diet plan duplication

## Role-Based Testing

Use different tokens to verify permission handling:

1. Admin Token (`auth_token`):
   - Should have access to all endpoints
   - Can create/edit/delete any resource

2. Client Token (`client_token`):
   - Should only be able to view own diet plans
   - Should not be able to create/edit diet plans
   - Should be able to view own profile

3. Trainer Token (`trainer_token`):
   - Should be able to view assigned clients
   - Should be able to view client diet plans
   - Should be able to create/edit diet plans for clients

## Common Issues

1. **Authentication Errors**:
   - Check that the token is set correctly
   - Tokens expire after inactivity
   - Re-run login requests to get fresh tokens

2. **404 Errors**:
   - Check that resources exist (IDs in URLs)
   - Make sure you've created prerequisite resources

3. **403 Forbidden**:
   - This indicates permission issues
   - Verify you're using the correct token for the operation
   - Check role and permission assignments

4. **Validation Errors**:
   - Pay attention to required fields
   - Check field type constraints (e.g., enums must use exact values)

## Testing Diet Plan Workflow

Here's a complete workflow to test the diet plan features:

1. Login as dietitian/admin
2. Create a client (or use existing)
3. Create a client profile
4. Create a diet plan
5. Create meal plans for days of the week
6. Add meals to each meal plan
7. View the complete diet plan
8. Duplicate the diet plan
9. Login as client
10. Verify the client can view their diet plan

This workflow tests the most important features of the diet planning system.
