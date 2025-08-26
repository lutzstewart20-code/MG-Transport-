# MG Transport Payment System

## Overview
This document describes the comprehensive payment system implemented for MG Transport Services, which includes multiple payment methods with dedicated forms and processing logic.

## Payment Methods

### 1. Bank Payment
- **BSP Online Banking**: Direct online payment through BSP's payment gateway
- **Manual Bank Transfer**: Traditional bank transfer with receipt verification

### 2. Card Payment
- **Online Card Payment**: Secure credit/debit card processing with comprehensive form
- **SMS Banking**: SMS-based payment with receipt upload

### 3. Cash Payment
- **Cash on Pickup**: Pay cash when collecting the vehicle

## File Structure

### Core Payment Files
- `payment_gateway.php` - Main payment method selection and routing
- `card_payment.php` - Online card payment processing
- `bsp_payment.php` - BSP online banking integration
- `sms_banking.php` - SMS banking receipt upload
- `bank_transfer_confirmation.php` - Manual bank transfer verification
- `payment_success.php` - Payment confirmation page

### Integration Files
- `booking.php` - Updated to redirect to payment gateway
- `my-bookings.php` - Shows payment status and options

## Payment Flow

### 1. Booking Submission
1. User submits booking with selected payment method
2. System creates booking with 'pending' payment status
3. User is redirected to payment gateway (except for cash payments)

### 2. Payment Processing
1. **Payment Gateway**: User selects payment method category
2. **Method Selection**: User chooses specific payment option
3. **Form Display**: Appropriate payment form is shown
4. **Payment Submission**: Payment details are processed
5. **Confirmation**: User is redirected to success page

### 3. Payment Methods Details

#### BSP Online Banking
- Integrates with BSP payment API
- Generates secure payment requests
- Handles callbacks and verification
- Updates booking status automatically

#### Online Card Payment
- Comprehensive card form with validation
- Billing address collection
- Real-time card preview
- Secure payment processing
- Transaction ID generation

#### SMS Banking
- Receipt upload functionality
- Payment verification workflow
- Admin notification system
- Status tracking

#### Manual Bank Transfer
- SMS verification code system
- Bank selection options
- Transfer confirmation
- Admin verification workflow

#### Cash Payment
- Immediate booking confirmation
- Pending admin verification
- Clear pickup instructions

## Form Fields

### Card Payment Form
- Card number (with formatting)
- Expiry month/year
- CVV
- Card holder name
- Billing address
- City, postal code, country

### SMS Banking Form
- Transaction ID
- Amount paid
- Payment date
- Bank name
- Account number
- Reference number
- Receipt file upload
- Additional notes

### Bank Transfer Form
- Bank selection (BSP, Kina Bank)
- Account name
- Account number
- Reference number
- Amount paid
- Payment date
- SMS verification code

## Security Features

### Data Protection
- Card numbers are masked and only last 4 digits stored
- All inputs are sanitized
- SQL injection prevention
- XSS protection

### Payment Verification
- SMS verification codes for bank transfers
- Receipt upload validation
- Admin verification workflow
- Transaction ID generation

### Session Management
- Secure session handling
- Payment state tracking
- Automatic cleanup of sensitive data

## Database Tables

### Required Tables
- `bookings` - Main booking information
- `payment_requests` - Payment processing requests
- `payment_receipts` - Payment receipts and verification
- `verification_codes` - SMS verification codes
- `invoices` - Generated invoices

### Key Fields
- `payment_method` - Type of payment selected
- `payment_status` - Current payment status
- `payment_details` - JSON encoded payment information
- `transaction_id` - Unique payment identifier

## Admin Features

### Payment Management
- View all payment requests
- Verify payment receipts
- Update payment statuses
- Generate reports
- Monitor payment analytics

### Notifications
- Payment completion alerts
- Receipt upload notifications
- Verification code requests
- Status change updates

## User Experience Features

### Interactive Elements
- Dynamic form display based on payment method
- Real-time card preview
- Bank selection with visual feedback
- Progress indicators
- Responsive design

### Validation
- Client-side form validation
- Server-side security checks
- Real-time error feedback
- Field-specific validation rules

## Integration Points

### External Services
- BSP Payment Gateway API
- SMS service for verification codes
- Email service for confirmations
- File upload handling

### Internal Systems
- User authentication
- Booking management
- Vehicle availability
- Invoice generation
- Notification system

## Configuration

### API Keys
- BSP API credentials
- SMS service configuration
- Email service settings

### Bank Details
- Account numbers
- Bank names and branches
- Reference number formats

### Payment Limits
- File upload sizes
- Verification code limits
- Payment timeouts

## Testing

### Test Scenarios
- All payment method flows
- Form validation
- Error handling
- Success scenarios
- Admin verification workflows

### Test Data
- Sample payment requests
- Test verification codes
- Mock payment responses
- Error simulation

## Deployment

### Requirements
- PHP 7.4+
- MySQL 5.7+
- SSL certificate
- File upload permissions
- Email service access

### Security Checklist
- HTTPS enabled
- File upload restrictions
- Input validation
- SQL injection prevention
- XSS protection
- Session security

## Maintenance

### Regular Tasks
- Monitor payment logs
- Verify payment statuses
- Clean up old verification codes
- Update bank information
- Review security logs

### Updates
- API endpoint updates
- Security patches
- Feature enhancements
- Bug fixes

## Support

### User Support
- Payment method explanations
- Troubleshooting guides
- Contact information
- FAQ section

### Technical Support
- Error logging
- Performance monitoring
- Backup procedures
- Recovery processes

## Future Enhancements

### Planned Features
- Mobile app integration
- Additional payment gateways
- Advanced analytics
- Automated reconciliation
- Multi-currency support

### Scalability
- Load balancing
- Database optimization
- Caching strategies
- API rate limiting

---

**Note**: This payment system is designed to be secure, user-friendly, and scalable. Regular security audits and updates are recommended to maintain the highest level of security and functionality.

